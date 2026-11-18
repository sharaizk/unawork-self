<?php

namespace Tests\Feature\Pod;

use App\Enums\PodDocument\PodDocumentStatus;
use App\Enums\PodDocument\PodDocumentType;
use App\Enums\PodDocument\PodDocumentUploadStatus;
use App\Enums\PodDocument\PodDocumentVersionStatus;
use App\Enums\PodUser\State;
use App\Enums\UserWorkspace\Permission as WorkspacePermission;
use App\Exceptions\PodDocument\CannotDeleteDocumentWithHistoryException;
use App\Exceptions\PodDocument\CannotDeletePublishedVersionException;
use App\Exceptions\PodDocument\CannotModifyImmutableVersionException;
use App\Exceptions\PodDocument\CannotPublishIncompleteUploadException;
use App\Exceptions\PodDocument\CannotPublishNonDraftVersionException;
use App\Models\Pod;
use App\Models\PodDocument;
use App\Models\PodDocumentVersion;
use App\Models\PodUser;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Services\PodDocument\PodDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Tests for UNA-020: POD Knowledge Base documents and version history.
 *
 * Verifies:
 *  - POD ownership and authorized business user management
 *  - Unauthorized access and boundaries
 *  - Applicant access boundaries
 *  - Version history preservation and immutability
 *  - Published-version selection for screening consumers
 */
class PodKnowledgeBaseTest extends PodTestCase
{
    private Pod $pod;
    private PodDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPodDocumentSchema();
        $this->service = app(PodDocumentService::class);

        // Create and publish a POD owned by $this->company
        $draft = $this->configuredDraft();
        $this->pod = $this->pods()->publish($this->company, $draft);
    }

    /**
     * Helper to associate a user to the POD in a specific state.
     */
    private function associateUserToPod(User $user, State $state, ?string $permission = null): PodUser
    {
        $membership = PodUser::findOrCreateMembership($user->id, $this->pod->id, [
            'permission' => $permission ?? 'Member',
            'state' => $state->value,
        ]);
        $membership->state = $state;
        $membership->save();

        if (in_array($state, State::activeMembers(), true) && $this->pod->workspace) {
            $uw = UserWorkspace::findOrCreateMembership($user->id, $this->pod->workspace->id, [
                'permission' => $permission ?? 'Member',
            ]);
            $uw->status = 'active';
            $uw->save();
        }

        return $membership;
    }

    // =========================================================================
    // 1. POD Ownership & Business User Authorization
    // =========================================================================

    public function test_pod_owner_can_create_and_manage_documents(): void
    {
        $this->actingAs($this->company);

        // Owner can access create page
        $this->get(route('pods.documents.create', $this->pod))
            ->assertOk();

        // Owner can store a new document
        $response = $this->post(route('pods.documents.store', $this->pod), [
            'title' => 'Engineering Handbook',
            'description' => 'Guidelines for engineering standards.',
            'type' => PodDocumentType::GUIDELINE->value,
            'content' => '# Engineering Handbook Content',
            'change_summary' => 'Initial release',
            'publish' => 1,
        ]);

        $document = PodDocument::where('pod_id', $this->pod->id)->first();
        $this->assertNotNull($document);
        $this->assertSame('Engineering Handbook', $document->title);
        $this->assertTrue($document->isPublished());
        $this->assertSame(1, $document->versions()->count());

        $response->assertRedirect(route('pods.documents.show', [$this->pod, $document]));
    }

    public function test_workspace_manager_with_users_edit_can_manage_documents(): void
    {
        $manager = $this->account('employee');
        $this->associateUserToPod($manager, State::OUTER_CIRCLE, 'Manager');

        // Grant USERS_EDIT permission in the POD workspace
        $uw = UserWorkspace::where('user_id', $manager->id)->where('workspace_id', $this->pod->workspace->id)->first();
        $uw->givePermissionTo(WorkspacePermission::USERS_EDIT->value);

        $this->actingAs($manager);

        // Manager can view create form
        $this->get(route('pods.documents.create', $this->pod))
            ->assertOk();

        // Manager can store document
        $this->post(route('pods.documents.store', $this->pod), [
            'title' => 'Manager Guide',
            'type' => PodDocumentType::PROCESS->value,
            'content' => 'Manager process guidelines.',
            'publish' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('pod_documents', [
            'pod_id' => $this->pod->id,
            'title' => 'Manager Guide',
            'status' => PodDocumentStatus::DRAFT->value,
        ]);
    }

    public function test_member_without_management_permission_cannot_manage_documents(): void
    {
        $member = $this->account('employee');
        $this->associateUserToPod($member, State::OUTER_CIRCLE);

        $this->actingAs($member);

        // Cannot view create form
        $this->get(route('pods.documents.create', $this->pod))
            ->assertForbidden();

        // Cannot store document
        $this->post(route('pods.documents.store', $this->pod), [
            'title' => 'Unauthorized Document',
            'type' => PodDocumentType::POLICY->value,
            'content' => 'Forbidden content',
        ])->assertForbidden();
    }

    // =========================================================================
    // 2. Unauthorized Access & Public Protection Boundaries
    // =========================================================================

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('pods.documents.index', $this->pod))
            ->assertRedirect(route('login'));
    }

    public function test_unrelated_user_is_forbidden_from_pod_documents(): void
    {
        $unrelated = $this->account('employee');
        $this->actingAs($unrelated);

        $this->get(route('pods.documents.index', $this->pod))
            ->assertForbidden();
    }

    public function test_removed_or_rejected_member_is_forbidden(): void
    {
        $rejectedUser = $this->account('employee');
        $this->associateUserToPod($rejectedUser, State::REJECTED);

        $this->actingAs($rejectedUser);
        $this->get(route('pods.documents.index', $this->pod))
            ->assertForbidden();

        $removedUser = $this->account('employee');
        $this->associateUserToPod($removedUser, State::REMOVED);

        $this->actingAs($removedUser);
        $this->get(route('pods.documents.index', $this->pod))
            ->assertForbidden();
    }

    // =========================================================================
    // 3. Applicant Access Boundaries
    // =========================================================================

    public function test_applicant_can_access_published_documents_only(): void
    {
        // Create one published document and one draft document
        $publishedDoc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Applicant Screening Rubric',
            'type' => PodDocumentType::SCREENING->value,
            'content' => 'Criteria for screening candidates.',
            'publish' => true,
        ]);

        $draftDoc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Internal Draft Notes',
            'type' => PodDocumentType::POLICY->value,
            'content' => 'Internal notes not ready for applicants.',
            'publish' => false,
        ]);

        $applicant = $this->account('employee');
        $this->associateUserToPod($applicant, State::PENDING_BUSINESS_APPROVAL);

        $this->actingAs($applicant);

        // Index page accessible by applicant
        $indexResponse = $this->get(route('pods.documents.index', $this->pod));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Applicant Screening Rubric');
        $indexResponse->assertDontSee('Internal Draft Notes');

        // Applicant can view the published document
        $this->get(route('pods.documents.show', [$this->pod, $publishedDoc]))
            ->assertOk()
            ->assertSee('Criteria for screening candidates.');

        // Applicant CANNOT view the draft document
        $this->get(route('pods.documents.show', [$this->pod, $draftDoc]))
            ->assertForbidden();

        // Applicant CANNOT access management endpoints
        $this->get(route('pods.documents.create', $this->pod))
            ->assertForbidden();
        $this->get(route('pods.documents.edit', [$this->pod, $publishedDoc]))
            ->assertForbidden();
        $this->post(route('pods.documents.archive', [$this->pod, $publishedDoc]))
            ->assertForbidden();
        $this->delete(route('pods.documents.destroy', [$this->pod, $publishedDoc]))
            ->assertForbidden();
    }

    public function test_applicant_cannot_view_draft_or_archived_versions(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Technical Assessment Guide',
            'type' => PodDocumentType::TECHNICAL->value,
            'content' => 'Published Version 1',
            'publish' => true,
        ]);
        $v1 = $doc->publishedVersion();

        // Create a new draft version v2
        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'content' => 'Draft Version 2 Secret Content',
            'change_summary' => 'Work in progress',
            'publish' => false,
        ]);

        $applicant = $this->account('employee');
        $this->associateUserToPod($applicant, State::MATCHED);

        $this->actingAs($applicant);

        // Can view published version v1
        $this->get(route('pods.documents.versions.show', [$this->pod, $doc, $v1]))
            ->assertOk()
            ->assertSee('Published Version 1');

        // CANNOT view draft version v2
        $this->get(route('pods.documents.versions.show', [$this->pod, $doc, $v2]))
            ->assertForbidden();

        // Publish v2 (which archives v1)
        $this->service->publishVersion($doc, $v2, $this->company);
        $v1->refresh();

        // Now v1 is ARCHIVED -> applicant can no longer view historical v1 directly
        $this->get(route('pods.documents.versions.show', [$this->pod, $doc, $v1]))
            ->assertForbidden();

        // Applicant can now view newly published v2
        $this->get(route('pods.documents.versions.show', [$this->pod, $doc, $v2]))
            ->assertOk()
            ->assertSee('Draft Version 2 Secret Content');
    }

    // =========================================================================
    // 4. Version History Preservation & Immutability
    // =========================================================================

    public function test_version_history_is_preserved_when_updating_published_document(): void
    {
        // 1. Create and publish version 1
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Security Policy',
            'type' => PodDocumentType::POLICY->value,
            'content' => 'Initial security rules (v1).',
            'change_summary' => 'Initial v1',
            'publish' => true,
        ]);

        $v1 = $doc->publishedVersion();
        $this->assertNotNull($v1);
        $this->assertSame(1, $v1->version_number);
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v1->status);

        // 2. Updating document content must NOT overwrite v1; it creates draft v2
        $this->service->updateDocument($doc, $this->company, [
            'content' => 'Revised security rules (v2 draft).',
            'change_summary' => 'Updated security protocol',
            'publish' => false,
        ]);

        $v1->refresh();
        $this->assertSame('Initial security rules (v1).', $v1->content, 'Published v1 must remain untouched.');
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v1->status);

        $v2 = $doc->fresh()->latestVersion();
        $this->assertSame(2, $v2->version_number);
        $this->assertSame(PodDocumentVersionStatus::DRAFT, $v2->status);
        $this->assertSame('Revised security rules (v2 draft).', $v2->content);

        // 3. Publishing v2 archives v1 and activates v2
        $this->service->publishVersion($doc, $v2, $this->company);

        $v1->refresh();
        $v2->refresh();

        $this->assertSame(PodDocumentVersionStatus::ARCHIVED, $v1->status);
        $this->assertNotNull($v1->archived_at);
        $this->assertSame('Initial security rules (v1).', $v1->content);

        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v2->status);
        $this->assertNotNull($v2->published_at);
        $this->assertNull($v2->archived_at);

        // Total version count is 2
        $this->assertSame(2, $doc->versions()->count());
    }

    public function test_cannot_modify_or_delete_published_or_archived_versions(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Immutable Guideline',
            'type' => PodDocumentType::GUIDELINE->value,
            'content' => 'Version 1 Content',
            'publish' => true,
        ]);
        $v1 = $doc->publishedVersion();

        // Attempting to modify immutable version 1 must throw
        $this->expectException(CannotModifyImmutableVersionException::class);
        $this->service->updateDraftVersion($doc, $v1, $this->company, [
            'content' => 'Trying to overwrite v1 content',
        ]);
    }

    public function test_cannot_delete_published_version(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Delete Protected Doc',
            'type' => PodDocumentType::GENERAL->value,
            'content' => 'Protected Content',
            'publish' => true,
        ]);
        $v1 = $doc->publishedVersion();

        $this->expectException(CannotDeletePublishedVersionException::class);
        $this->service->deleteDraftVersion($doc, $v1);
    }

    public function test_cannot_delete_document_with_published_history(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'History Protected Doc',
            'type' => PodDocumentType::GENERAL->value,
            'content' => 'Important History',
            'publish' => true,
        ]);

        $this->expectException(CannotDeleteDocumentWithHistoryException::class);
        $this->service->deleteDocument($doc);
    }

    public function test_can_delete_purely_draft_document_and_draft_versions(): void
    {
        // Create draft document
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Scratch Document',
            'type' => PodDocumentType::GENERAL->value,
            'content' => 'Scratch draft',
            'publish' => false,
        ]);
        $this->assertTrue($doc->isDraft());

        // Create a second draft version
        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'content' => 'Scratch draft 2',
            'publish' => false,
        ]);
        $this->assertSame(2, $doc->versions()->count());

        // Deleting draft v2 succeeds
        $this->service->deleteDraftVersion($doc, $v2);
        $this->assertSame(1, $doc->fresh()->versions()->count());

        // Deleting the draft document succeeds
        $deleted = $this->service->deleteDocument($doc);
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('pod_documents', ['id' => $doc->id]);
    }

    // =========================================================================
    // 5. Published-Version Selection for Screening Consumers
    // =========================================================================

    public function test_screening_consumer_receives_only_active_published_version(): void
    {
        // 1. Un-published draft document returns NULL to screening
        $draftDoc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Draft Screening Rubric',
            'type' => PodDocumentType::SCREENING->value,
            'content' => 'Draft rubric content',
            'publish' => false,
        ]);

        $screeningResult = $this->service->getPublishedVersionForScreening($this->pod, $draftDoc->id);
        $this->assertNull($screeningResult, 'Draft document must never be returned to screening.');

        // 2. Published document returns its published version
        $publishedDoc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Active Screening Rubric',
            'type' => PodDocumentType::SCREENING->value,
            'content' => 'Active Screening v1',
            'publish' => true,
        ]);

        $v1 = $publishedDoc->publishedVersion();
        $screeningResult = $this->service->getPublishedVersionForScreening($this->pod, $publishedDoc->id);
        $this->assertNotNull($screeningResult);
        $this->assertSame($v1->id, $screeningResult->id);
        $this->assertSame('Active Screening v1', $screeningResult->content);

        // 3. Drafting v2 does NOT affect screening: screening still receives v1
        $v2 = $this->service->createDraftVersion($publishedDoc, $this->company, [
            'content' => 'Unpublished v2 Rubric',
            'publish' => false,
        ]);

        $screeningResult = $this->service->getPublishedVersionForScreening($this->pod, $publishedDoc->id);
        $this->assertSame($v1->id, $screeningResult->id, 'Screening must continue receiving v1 while v2 is a draft.');
        $this->assertNotSame($v2->id, $screeningResult->id);

        // 4. Publishing v2 updates the screening version to v2
        $this->service->publishVersion($publishedDoc, $v2, $this->company);

        $screeningResult = $this->service->getPublishedVersionForScreening($this->pod, $publishedDoc->id);
        $this->assertSame($v2->id, $screeningResult->id);
        $this->assertSame('Unpublished v2 Rubric', $screeningResult->content);

        // 5. If document is archived, screening receives NULL
        $this->service->archiveDocument($publishedDoc, $this->company);

        $screeningResult = $this->service->getPublishedVersionForScreening($this->pod, $publishedDoc->id);
        $this->assertNull($screeningResult, 'Archived document must never be returned to screening.');

        // 6. Requesting document with wrong POD returns NULL
        $otherCompany = $this->account('company');
        $otherPod = $this->pods()->publish($otherCompany, $this->configuredDraft($otherCompany));

        $wrongPodResult = $this->service->getPublishedVersionForScreening($otherPod, $publishedDoc->id);
        $this->assertNull($wrongPodResult, 'Document from another POD must never be returned.');
    }

    // =========================================================================
    // 6. Focused Regression Tests for Code Review Findings
    // =========================================================================

    public function test_publishing_archived_or_already_published_version_is_rejected_without_changing_it(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Publish Guard Document',
            'type' => PodDocumentType::POLICY->value,
            'content' => 'Original Content v1',
            'change_summary' => 'Initial v1',
            'publish' => true,
        ]);

        $v1 = $doc->publishedVersion();
        $originalPublishedAt = $v1->published_at;

        // 1. Attempt to re-publish an already-published version
        try {
            $this->service->publishVersion($doc, $v1, $this->company);
            $this->fail('Expected CannotPublishNonDraftVersionException when publishing an already published version.');
        } catch (CannotPublishNonDraftVersionException $e) {
            $this->assertStringContainsString('Only draft document versions can be published', $e->getMessage());
        }

        // Verify v1 remains completely unchanged
        $v1->refresh();
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v1->status);
        $this->assertTrue($v1->published_at->equalTo($originalPublishedAt));
        $this->assertNull($v1->archived_at);
        $this->assertSame('Original Content v1', $v1->content);

        // 2. Draft and publish v2, which archives v1
        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'content' => 'Content v2',
            'publish' => false,
        ]);
        $this->service->publishVersion($doc, $v2, $this->company);

        $v1->refresh();
        $this->assertSame(PodDocumentVersionStatus::ARCHIVED, $v1->status);
        $originalArchivedAt = $v1->archived_at;
        $this->assertNotNull($originalArchivedAt);

        // 3. Attempt to re-publish the archived v1
        try {
            $this->service->publishVersion($doc, $v1, $this->company);
            $this->fail('Expected CannotPublishNonDraftVersionException when publishing an archived version.');
        } catch (CannotPublishNonDraftVersionException $e) {
            $this->assertStringContainsString('Only draft document versions can be published', $e->getMessage());
        }

        // Verify archived v1 remains completely unchanged
        $v1->refresh();
        $this->assertSame(PodDocumentVersionStatus::ARCHIVED, $v1->status);
        $this->assertTrue($v1->archived_at->equalTo($originalArchivedAt));
        $this->assertTrue($v1->published_at->equalTo($originalPublishedAt));
        $this->assertSame('Original Content v1', $v1->content);
    }

    /**
     * Verifies that sequential publication of multiple draft versions archives previous active
     * versions, leaving exactly one active published version at any time, and rejecting any attempt
     * to republish superseded versions.
     */
    public function test_sequential_draft_publications_supersede_prior_versions_leaving_single_active_published_version(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Publication Lifecycle Document',
            'type' => PodDocumentType::TECHNICAL->value,
            'content' => 'Base v1',
            'publish' => true,
        ]);

        // Draft v2
        $v2 = PodDocumentVersion::create([
            'pod_document_id' => $doc->id,
            'version_number' => 2,
            'title' => $doc->title,
            'content' => 'Draft Content v2',
            'status' => PodDocumentVersionStatus::DRAFT,
            'created_by' => $this->company->id,
        ]);

        // Draft v3
        $v3 = PodDocumentVersion::create([
            'pod_document_id' => $doc->id,
            'version_number' => 3,
            'title' => $doc->title,
            'content' => 'Draft Content v3',
            'status' => PodDocumentVersionStatus::DRAFT,
            'created_by' => $this->company->id,
        ]);

        // Publish v2
        $this->service->publishVersion($doc, $v2, $this->company);

        $this->assertSame(1, PodDocumentVersion::where('pod_document_id', $doc->id)->where('status', 'published')->count());
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v2->fresh()->status);
        $this->assertSame(PodDocumentVersionStatus::ARCHIVED, $doc->versions()->where('version_number', 1)->first()->status);

        // Publish v3
        $this->service->publishVersion($doc, $v3, $this->company);

        // Exactly one published version remains (v3)
        $this->assertSame(1, PodDocumentVersion::where('pod_document_id', $doc->id)->where('status', 'published')->count());
        $this->assertSame(2, PodDocumentVersion::where('pod_document_id', $doc->id)->where('status', 'archived')->count());
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v3->fresh()->status);
        $this->assertSame(PodDocumentVersionStatus::ARCHIVED, $v2->fresh()->status);
        $this->assertNotNull($v2->fresh()->archived_at);

        // Re-publishing v2 now fails and does not alter the single active published version
        try {
            $this->service->publishVersion($doc, $v2, $this->company);
            $this->fail('Expected rejection when publishing already archived version.');
        } catch (CannotPublishNonDraftVersionException $e) {
            // expected
        }

        $this->assertSame(1, PodDocumentVersion::where('pod_document_id', $doc->id)->where('status', 'published')->count());
        $this->assertSame($v3->id, $doc->fresh()->publishedVersion()->id);
    }

    public function test_archiving_document_archives_active_version_and_removes_it_from_screening(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Screening Rubric to Archive',
            'type' => PodDocumentType::SCREENING->value,
            'content' => 'Important Screening Guidelines',
            'publish' => true,
        ]);

        $v1 = $doc->publishedVersion();
        $originalPublishedAt = $v1->published_at;
        $this->assertNotNull($originalPublishedAt);

        // Screening returns the active version
        $this->assertNotNull($this->service->getPublishedVersionForScreening($this->pod, $doc->id));

        // Archive the document
        $this->service->archiveDocument($doc, $this->company);

        // Document is archived
        $this->assertTrue($doc->fresh()->isArchived());

        // Active version is transitioned to archived with archived_at set
        $v1->refresh();
        $this->assertTrue($v1->isArchived());
        $this->assertNotNull($v1->archived_at);
        $this->assertTrue($v1->published_at->equalTo($originalPublishedAt), 'Original published_at must be preserved.');
        $this->assertSame('Important Screening Guidelines', $v1->content, 'Historical content must be preserved.');

        // Document is excluded from published documents list
        $this->assertFalse($this->service->getPublishedDocuments($this->pod)->contains('id', $doc->id));

        // Screening returns NULL for archived document and version
        $this->assertNull($this->service->getPublishedVersionForScreening($this->pod, $doc->id));
    }

    public function test_screening_selection_remains_deterministic_and_excludes_archived_documents_and_versions(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Deterministic Selection Doc',
            'type' => PodDocumentType::SCREENING->value,
            'content' => 'Version 1 Content',
            'publish' => true,
        ]);
        $v1 = $doc->publishedVersion();

        // Draft and publish v2
        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'content' => 'Version 2 Content',
            'publish' => true,
        ]);

        // Simulating legacy/corrupted state with multiple published versions in DB
        PodDocumentVersion::where('id', $v1->id)->update([
            'status' => PodDocumentVersionStatus::PUBLISHED->value,
        ]);

        // Both v1 and v2 now have status='published' in DB
        $this->assertSame(2, PodDocumentVersion::where('pod_document_id', $doc->id)->where('status', 'published')->count());

        // Deterministic selection: MUST return v2 (latest version by version_number desc, published_at desc, id desc)
        $selected = $this->service->getPublishedVersionForScreening($this->pod, $doc->id);
        $this->assertNotNull($selected);
        $this->assertSame($v2->id, $selected->id, 'Screening must deterministically select the highest version.');
        $this->assertSame('Version 2 Content', $selected->content);

        // If the document is marked archived, screening selection must return NULL even if versions have status='published'
        $doc->status = PodDocumentStatus::ARCHIVED;
        $doc->save();

        $this->assertNull($this->service->getPublishedVersionForScreening($this->pod, $doc->id));

        // When document is published but all versions are archived, screening returns NULL
        $doc->status = PodDocumentStatus::PUBLISHED;
        $doc->save();

        PodDocumentVersion::where('pod_document_id', $doc->id)->update([
            'status' => PodDocumentVersionStatus::ARCHIVED->value,
        ]);

        $this->assertNull($this->service->getPublishedVersionForScreening($this->pod, $doc->id));
    }

    public function test_get_published_documents_returns_only_single_selected_active_version_even_with_duplicate_published_rows(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Duplicate Published State Doc',
            'type' => PodDocumentType::SCREENING->value,
            'content' => 'Version 1 Content',
            'publish' => true,
        ]);
        $v1 = $doc->publishedVersion();

        // Draft and publish v2
        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'content' => 'Version 2 Content',
            'publish' => true,
        ]);

        // Artificially corrupt database to create duplicate published versions
        PodDocumentVersion::where('id', $v1->id)->update([
            'status' => PodDocumentVersionStatus::PUBLISHED->value,
        ]);

        $this->assertSame(2, PodDocumentVersion::where('pod_document_id', $doc->id)->where('status', 'published')->count());

        // Call getPublishedDocuments
        $publishedDocs = $this->service->getPublishedDocuments($this->pod);
        $retrievedDoc = $publishedDocs->firstWhere('id', $doc->id);

        $this->assertNotNull($retrievedDoc);
        // Callers must receive exactly one published version per document
        $this->assertSame(1, $retrievedDoc->versions->count(), 'Callers must receive only one published version per document.');
        $this->assertSame($v2->id, $retrievedDoc->versions->first()->id, 'The selected version must be the deterministically latest version.');
        $this->assertFalse($retrievedDoc->versions->contains('id', $v1->id), 'Duplicate older published versions must be excluded from the relation.');
    }

    public function test_draft_edit_rechecks_version_state_and_rejects_modification_if_published_prior_to_edit(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Race Guard Document',
            'type' => PodDocumentType::TECHNICAL->value,
            'content' => 'Base v1',
            'publish' => true,
        ]);

        // Draft v2
        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'content' => 'Original Draft v2 Content',
            'change_summary' => 'Initial v2 draft',
            'publish' => false,
        ]);
        $this->assertTrue($v2->isDraft());
        $this->assertNull($v2->published_at);

        // Transition v2 to published (simulating publish occurring before the updateDraftVersion save)
        $this->service->publishVersion($doc, $v2, $this->company);
        $v2->refresh();
        $this->assertTrue($v2->isPublished());
        $publishedAt = $v2->published_at;
        $this->assertNotNull($publishedAt);

        // Attempting to update v2 now must be rejected by the under-lock recheck
        try {
            $this->service->updateDraftVersion($doc, $v2, $this->company, [
                'content' => 'Late Overwrite Content',
                'change_summary' => 'Attempted late edit',
            ]);
            $this->fail('Expected CannotModifyImmutableVersionException when attempting to edit a published version.');
        } catch (CannotModifyImmutableVersionException $e) {
            // expected
        }

        // Verify that v2 content, status, and timestamps remain completely unchanged
        $v2->refresh();
        $this->assertSame('Original Draft v2 Content', $v2->content);
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v2->status);
        $this->assertTrue($v2->published_at->equalTo($publishedAt));
        $this->assertNull($v2->archived_at);
    }

    public function test_update_document_creates_new_draft_instead_of_overwriting_when_latest_version_is_published(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Document Update Concurrency',
            'type' => PodDocumentType::GUIDELINE->value,
            'content' => 'Published v1 Content',
            'publish' => true,
        ]);
        $v1 = $doc->publishedVersion();

        // Updating document with content when v1 is published must create v2 without modifying v1
        $updatedDoc = $this->service->updateDocument($doc, $this->company, [
            'content' => 'Draft v2 Content',
            'change_summary' => 'New section added',
            'publish' => false,
        ]);

        $this->assertSame(2, $updatedDoc->versions()->count());

        $v1->refresh();
        $this->assertSame('Published v1 Content', $v1->content);
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v1->status);

        $v2 = $updatedDoc->latestVersion();
        $this->assertSame(2, $v2->version_number);
        $this->assertSame('Draft v2 Content', $v2->content);
        $this->assertSame(PodDocumentVersionStatus::DRAFT, $v2->status);
    }

    public function test_draft_delete_rechecks_version_state_and_rejects_deletion_if_published_prior_to_delete(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Delete Recheck Document',
            'type' => PodDocumentType::GENERAL->value,
            'content' => 'Version 1',
            'publish' => true,
        ]);

        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'content' => 'Version 2 Draft',
            'publish' => false,
        ]);

        // Publish v2 before delete
        $this->service->publishVersion($doc, $v2, $this->company);
        $v2->refresh();

        try {
            $this->service->deleteDraftVersion($doc, $v2);
            $this->fail('Expected CannotDeletePublishedVersionException when deleting a published version.');
        } catch (CannotDeletePublishedVersionException $e) {
            // expected
        }

        // Verify version 2 still exists and is published
        $this->assertDatabaseHas('pod_document_versions', [
            'id' => $v2->id,
            'status' => PodDocumentVersionStatus::PUBLISHED->value,
        ]);
    }

    // =========================================================================
    // 6. UNA-021: POD Onboarding Materials, Uploads, Links & Reordering
    // =========================================================================

    public function test_business_user_can_upload_pdf_material_privately(): void
    {
        Storage::fake('local');
        $this->actingAs($this->company);

        $file = UploadedFile::fake()->create('handbook.pdf', 500, 'application/pdf');

        $response = $this->post(route('pods.documents.store', $this->pod), [
            'title' => 'Employee Handbook',
            'type' => PodDocumentType::ONBOARDING->value,
            'file' => $file,
            'publish' => 1,
        ]);

        $response->assertRedirect();

        $doc = PodDocument::where('pod_id', $this->pod->id)->where('title', 'Employee Handbook')->first();
        $this->assertNotNull($doc);
        $this->assertTrue($doc->isPublished());

        $version = $doc->publishedVersion();
        $this->assertNotNull($version);
        $this->assertSame(PodDocumentUploadStatus::READY, $version->upload_status);
        $this->assertSame('handbook.pdf', $version->file_name);
        $this->assertSame('application/pdf', $version->file_mime);
        $this->assertNotNull($version->file_path);
        Storage::disk('local')->assertExists($version->file_path);
    }

    public function test_business_user_can_upload_doc_and_docx_materials(): void
    {
        Storage::fake('local');
        $this->actingAs($this->company);

        $docx = UploadedFile::fake()->create('spec.docx', 300, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Technical Specs',
            'type' => PodDocumentType::TECHNICAL,
            'file' => $docx,
            'publish' => true,
        ]);

        $this->assertSame('spec.docx', $doc->publishedVersion()->file_name);
        $this->assertSame(PodDocumentUploadStatus::READY, $doc->publishedVersion()->upload_status);
        Storage::disk('local')->assertExists($doc->publishedVersion()->file_path);
    }

    public function test_business_user_can_create_link_and_text_material(): void
    {
        $this->actingAs($this->company);

        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Team Notion Board',
            'type' => PodDocumentType::GENERAL,
            'link_url' => 'https://notion.so/unawork/team',
            'content' => 'Link to shared team workspace and backlog.',
            'publish' => true,
        ]);

        $this->assertSame('https://notion.so/unawork/team', $doc->publishedVersion()->link_url);
        $this->assertSame(PodDocumentUploadStatus::READY, $doc->publishedVersion()->upload_status);
        $this->assertTrue($doc->publishedVersion()->isLink());
    }

    public function test_disallowed_file_extensions_and_mimes_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->company);

        $badFile = UploadedFile::fake()->create('script.sh', 50, 'text/x-shellscript');

        $response = $this->from(route('pods.documents.create', $this->pod))
            ->post(route('pods.documents.store', $this->pod), [
                'title' => 'Malicious Script',
                'type' => PodDocumentType::TECHNICAL->value,
                'file' => $badFile,
            ]);

        $response->assertRedirect(route('pods.documents.create', $this->pod));
        $response->assertSessionHasErrors(['file']);
    }

    public function test_file_exceeding_configured_size_limit_is_rejected(): void
    {
        Storage::fake('local');
        config(['pod_materials.max_file_size_kb' => 100]); // 100 KB limit
        $this->actingAs($this->company);

        $largeFile = UploadedFile::fake()->create('huge.pdf', 500, 'application/pdf'); // 500 KB > 100 KB

        $result = $this->service->storeUploadedFile($largeFile, $this->pod);
        $this->assertSame(PodDocumentUploadStatus::FAILED, $result['upload_status']);
        $this->assertStringContainsString('exceeds the maximum allowed limit', $result['failure_message']);
    }

    public function test_cannot_publish_version_with_incomplete_or_failed_upload(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Failed Upload Doc',
            'type' => PodDocumentType::ONBOARDING,
            'content' => 'Placeholder text',
            'upload_status' => PodDocumentUploadStatus::FAILED,
            'failure_message' => 'Disk full during write',
            'publish' => false,
        ]);

        $draftVersion = $doc->draftVersion();
        $this->assertSame(PodDocumentUploadStatus::FAILED, $draftVersion->upload_status);

        try {
            $this->service->publishVersion($doc, $draftVersion, $this->company);
            $this->fail('Expected CannotPublishIncompleteUploadException when publishing failed upload.');
        } catch (CannotPublishIncompleteUploadException $e) {
            $this->assertStringContainsString('pending or failed upload status', $e->getMessage());
        }

        // Verify version was NOT published
        $draftVersion->refresh();
        $this->assertSame(PodDocumentVersionStatus::DRAFT, $draftVersion->status);
    }

    public function test_failed_or_incomplete_uploads_are_hidden_from_applicants(): void
    {
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Internal Processing Doc',
            'type' => PodDocumentType::ONBOARDING,
            'content' => 'Incomplete upload content',
            'upload_status' => PodDocumentUploadStatus::FAILED,
            'failure_message' => 'Processing timeout',
            'publish' => false,
        ]);

        $applicant = User::factory()->create();
        $this->associateUserToPod($applicant, State::APPLICANTS_OUTER_CIRCLE);

        $this->actingAs($applicant);

        // Index does not show the failed/draft document
        $response = $this->get(route('pods.documents.index', $this->pod));
        $response->assertOk();
        $response->assertDontSee('Internal Processing Doc');

        // Show returns 404 for applicants
        $showResponse = $this->get(route('pods.documents.show', [$this->pod, $doc]));
        $showResponse->assertNotFound();
    }

    public function test_material_replacement_creates_new_version_and_preserves_prior_file_history(): void
    {
        Storage::fake('local');

        $file1 = UploadedFile::fake()->create('policy_v1.pdf', 100, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Security Policy',
            'type' => PodDocumentType::POLICY,
            'file' => $file1,
            'change_summary' => 'Initial v1 policy',
            'publish' => true,
        ]);

        $v1 = $doc->publishedVersion();
        $v1Path = $v1->file_path;
        $this->assertNotNull($v1Path);
        Storage::disk('local')->assertExists($v1Path);

        // Replace material with v2 file
        $file2 = UploadedFile::fake()->create('policy_v2.pdf', 150, 'application/pdf');
        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'file' => $file2,
            'change_summary' => 'Updated 2026 security requirements',
            'publish' => true,
        ]);

        $v1->refresh();
        $v2->refresh();

        // v1 transitioned to archived, but its file is still intact
        $this->assertSame(PodDocumentVersionStatus::ARCHIVED, $v1->status);
        $this->assertSame($v1Path, $v1->file_path);
        Storage::disk('local')->assertExists($v1Path);

        // v2 is published with the new file
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v2->status);
        $this->assertSame('policy_v2.pdf', $v2->file_name);
        Storage::disk('local')->assertExists($v2->file_path);
    }

    public function test_reordering_is_scoped_to_pod_and_saved_transactionally(): void
    {
        $doc1 = $this->service->createDocument($this->pod, $this->company, ['title' => 'Doc 1', 'type' => PodDocumentType::GENERAL, 'content' => 'Content 1']);
        $doc2 = $this->service->createDocument($this->pod, $this->company, ['title' => 'Doc 2', 'type' => PodDocumentType::GENERAL, 'content' => 'Content 2']);
        $doc3 = $this->service->createDocument($this->pod, $this->company, ['title' => 'Doc 3', 'type' => PodDocumentType::GENERAL, 'content' => 'Content 3']);

        // Initial positions 1, 2, 3
        $this->assertSame(1, $doc1->fresh()->position);
        $this->assertSame(2, $doc2->fresh()->position);
        $this->assertSame(3, $doc3->fresh()->position);

        // Reorder: 3, 1, 2
        $this->actingAs($this->company);
        $response = $this->post(route('pods.documents.reorder', $this->pod), [
            'ordered_ids' => [$doc3->id, $doc1->id, $doc2->id],
        ]);
        $response->assertRedirect(route('pods.documents.index', $this->pod));

        $this->assertSame(1, $doc3->fresh()->position);
        $this->assertSame(2, $doc1->fresh()->position);
        $this->assertSame(3, $doc2->fresh()->position);

        // Attempt cross-POD reordering with document belonging to another POD
        $draftOther = $this->configuredDraft();
        $otherPod = $this->pods()->publish($this->company, $draftOther);
        $foreignDoc = $this->service->createDocument($otherPod, $this->company, ['title' => 'Foreign Doc', 'type' => PodDocumentType::GENERAL, 'content' => 'Foreign']);

        try {
            $this->service->reorderDocuments($this->pod, [$foreignDoc->id, $doc1->id]);
            $this->fail('Expected InvalidArgumentException when reordering with foreign document ID.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('do not belong to this POD', $e->getMessage());
        }

        // Verify POD 1 positions remain untouched
        $this->assertSame(1, $doc3->fresh()->position);
        $this->assertSame(2, $doc1->fresh()->position);
        $this->assertSame(3, $doc2->fresh()->position);
    }

    public function test_protected_file_download_authorization_and_applicant_rules(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Onboarding Agreement',
            'type' => PodDocumentType::ONBOARDING,
            'file' => $file,
            'publish' => true,
        ]);

        $publishedVersion = $doc->publishedVersion();

        // 1. Business manager can download
        $this->actingAs($this->company);
        $downloadRes = $this->get(route('pods.documents.versions.download', [$this->pod, $doc, $publishedVersion]));
        $downloadRes->assertOk();

        // 2. Associated applicant can download published version
        $applicant = User::factory()->create();
        $this->associateUserToPod($applicant, State::APPLICANTS_OUTER_CIRCLE);

        $this->actingAs($applicant);
        $applicantDownloadRes = $this->get(route('pods.documents.versions.download', [$this->pod, $doc, $publishedVersion]));
        $applicantDownloadRes->assertOk();

        // 3. Draft version cannot be downloaded by applicant
        $draftFile = UploadedFile::fake()->create('draft_contract.pdf', 200, 'application/pdf');
        $draftVersion = $this->service->createDraftVersion($doc, $this->company, [
            'file' => $draftFile,
            'publish' => false,
        ]);

        $applicantDraftDownload = $this->get(route('pods.documents.versions.download', [$this->pod, $doc, $draftVersion]));
        $applicantDraftDownload->assertForbidden();

        // 4. Unrelated user gets 403 on published download
        $stranger = User::factory()->create();
        $this->actingAs($stranger);
        $strangerRes = $this->get(route('pods.documents.versions.download', [$this->pod, $doc, $publishedVersion]));
        $strangerRes->assertForbidden();

        // 5. Unauthenticated guest gets redirected to login (302)
        auth()->logout();
        $guestRes = $this->get(route('pods.documents.versions.download', [$this->pod, $doc, $publishedVersion]));
        $guestRes->assertRedirect(route('login'));
    }

    public function test_screening_interface_excludes_incomplete_or_failed_uploads(): void
    {
        $docGood = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Ready Document',
            'type' => PodDocumentType::SCREENING,
            'content' => 'Valid screening guidelines',
            'publish' => true,
        ]);

        $docFailed = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Failed Upload Document',
            'type' => PodDocumentType::SCREENING,
            'content' => 'Failed guidelines',
            'upload_status' => PodDocumentUploadStatus::FAILED,
            'publish' => false,
        ]);

        // Published documents collection excludes incomplete/failed uploads
        $publishedDocs = $this->service->getPublishedDocuments($this->pod, PodDocumentType::SCREENING);
        $this->assertTrue($publishedDocs->contains('id', $docGood->id));
        $this->assertFalse($publishedDocs->contains('id', $docFailed->id));

        // Direct screening query excludes failed document
        $this->assertNull($this->service->getPublishedVersionForScreening($this->pod, $docFailed->id));
        $this->assertNotNull($this->service->getPublishedVersionForScreening($this->pod, $docGood->id));
    }

    public function test_public_disk_configuration_is_rejected(): void
    {
        Storage::fake('public');
        config(['pod_materials.disk' => 'public']);

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        try {
            $this->service->storeUploadedFile($file, $this->pod);
            $this->fail('Expected DomainException when configured disk is public.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('cannot be stored on a public disk', $e->getMessage());
        }

        // Direct assertion on assertPrivateDisk
        try {
            $this->service->assertPrivateDisk('public');
            $this->fail('Expected DomainException when assertPrivateDisk called with public.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('cannot be stored on a public disk', $e->getMessage());
        }
    }

    public function test_disk_with_public_visibility_is_rejected(): void
    {
        config([
            'filesystems.disks.custom_public_cloud' => [
                'driver' => 'local',
                'root' => storage_path('app/custom_cloud'),
                'visibility' => 'public',
            ],
            'pod_materials.disk' => 'custom_public_cloud',
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        try {
            $this->service->storeUploadedFile($file, $this->pod);
            $this->fail('Expected DomainException when disk has public visibility.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('public', $e->getMessage());
        }
    }

    public function test_replacing_draft_file_removes_old_file_from_private_disk_after_commit(): void
    {
        Storage::fake('local');

        $initialFile = UploadedFile::fake()->create('draft_initial.pdf', 100, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Draft Handbook',
            'type' => PodDocumentType::ONBOARDING,
            'file' => $initialFile,
            'publish' => false,
        ]);

        $draftVersion = $doc->draftVersion();
        $initialPath = $draftVersion->file_path;
        $this->assertNotNull($initialPath);
        Storage::disk('local')->assertExists($initialPath);

        // Replace the draft file
        $replacementFile = UploadedFile::fake()->create('draft_replacement.pdf', 120, 'application/pdf');
        $this->service->updateDraftVersion($doc, $draftVersion, $this->company, [
            'file' => $replacementFile,
            'change_summary' => 'Replaced with draft v2',
        ]);

        $draftVersion->refresh();
        $replacementPath = $draftVersion->file_path;
        $this->assertNotNull($replacementPath);
        $this->assertNotSame($initialPath, $replacementPath);

        // Old file must be deleted from private storage
        Storage::disk('local')->assertMissing($initialPath);
        // New file must exist
        Storage::disk('local')->assertExists($replacementPath);
    }

    public function test_retaining_same_file_path_does_not_delete_file(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('draft_keep.pdf', 100, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Draft Guidelines',
            'type' => PodDocumentType::GUIDELINE,
            'file' => $file,
            'publish' => false,
        ]);

        $draftVersion = $doc->draftVersion();
        $keepPath = $draftVersion->file_path;
        $this->assertNotNull($keepPath);
        Storage::disk('local')->assertExists($keepPath);

        // Update draft text without replacing file
        $this->service->updateDraftVersion($doc, $draftVersion, $this->company, [
            'content' => 'Updated guideline instructions without changing file',
            'change_summary' => 'Updated instructions only',
        ]);

        $draftVersion->refresh();
        $this->assertSame($keepPath, $draftVersion->file_path);

        // File must still exist on storage
        Storage::disk('local')->assertExists($keepPath);
    }

    public function test_published_and_archived_files_remain_untouched_when_draft_replaces_material(): void
    {
        Storage::fake('local');

        $v1File = UploadedFile::fake()->create('published_v1.pdf', 150, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Company Policy',
            'type' => PodDocumentType::POLICY,
            'file' => $v1File,
            'publish' => true,
        ]);

        $v1 = $doc->publishedVersion();
        $v1Path = $v1->file_path;
        $this->assertNotNull($v1Path);
        Storage::disk('local')->assertExists($v1Path);

        // Create a new draft version
        $v2File = UploadedFile::fake()->create('draft_v2.pdf', 180, 'application/pdf');
        $v2 = $this->service->createDraftVersion($doc, $this->company, [
            'file' => $v2File,
            'change_summary' => 'Draft for upcoming policy revision',
            'publish' => false,
        ]);

        $v2Path = $v2->file_path;
        Storage::disk('local')->assertExists($v1Path);
        Storage::disk('local')->assertExists($v2Path);

        // Now replace draft v2's file with v2_revised.pdf
        $v2RevisedFile = UploadedFile::fake()->create('draft_v2_revised.pdf', 200, 'application/pdf');
        $this->service->updateDraftVersion($doc, $v2, $this->company, [
            'file' => $v2RevisedFile,
            'change_summary' => 'Revised draft v2 file',
        ]);

        $v2->refresh();
        $v2RevisedPath = $v2->file_path;

        // 1. Published v1 file must STILL exist (immutable history preserved)
        Storage::disk('local')->assertExists($v1Path);

        // 2. Intermediate replaced draft file was cleaned up
        Storage::disk('local')->assertMissing($v2Path);

        // 3. New draft file exists
        Storage::disk('local')->assertExists($v2RevisedPath);
    }

    public function test_replacing_draft_file_via_update_document_cleans_up_old_draft_file(): void
    {
        Storage::fake('local');

        $file1 = UploadedFile::fake()->create('doc_draft_v1.pdf', 100, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Setup Guide',
            'type' => PodDocumentType::PROCESS,
            'file' => $file1,
            'publish' => false,
        ]);

        $v1Path = $doc->draftVersion()->file_path;
        $this->assertNotNull($v1Path);
        Storage::disk('local')->assertExists($v1Path);

        // Update via updateDocument with new file
        $file2 = UploadedFile::fake()->create('doc_draft_v2.pdf', 150, 'application/pdf');
        $this->service->updateDocument($doc, $this->company, [
            'title' => 'Setup Guide Updated',
            'file' => $file2,
            'change_summary' => 'Replaced draft file',
        ]);

        $doc->refresh();
        $v2Path = $doc->draftVersion()->file_path;

        Storage::disk('local')->assertMissing($v1Path);
        Storage::disk('local')->assertExists($v2Path);
    }

    private function createFailingUploadedFile(string $name = 'failing.pdf', string $error = 'Disk write failure'): UploadedFile
    {
        $mock = $this->createMock(UploadedFile::class);
        $mock->method('getClientOriginalName')->willReturn($name);
        $mock->method('getClientOriginalExtension')->willReturn('pdf');
        $mock->method('getMimeType')->willReturn('application/pdf');
        $mock->method('getSize')->willReturn(102400);
        $mock->method('store')->willThrowException(new \RuntimeException($error));

        return $mock;
    }

    private function createNullStorageUploadedFile(string $name = 'null_storage.pdf'): UploadedFile
    {
        $mock = $this->createMock(UploadedFile::class);
        $mock->method('getClientOriginalName')->willReturn($name);
        $mock->method('getClientOriginalExtension')->willReturn('pdf');
        $mock->method('getMimeType')->willReturn('application/pdf');
        $mock->method('getSize')->willReturn(102400);
        $mock->method('store')->willReturn(false);

        return $mock;
    }

    public function test_failed_storage_during_update_draft_version_preserves_existing_attachment_and_rejects_update(): void
    {
        Storage::fake('local');

        $initialFile = UploadedFile::fake()->create('draft_orig.pdf', 100, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Draft Handbook',
            'type' => PodDocumentType::ONBOARDING,
            'file' => $initialFile,
            'publish' => false,
        ]);

        $draftVersion = $doc->draftVersion();
        $initialPath = $draftVersion->file_path;
        $this->assertNotNull($initialPath);
        Storage::disk('local')->assertExists($initialPath);

        // Attempt to replace draft attachment with a failing file upload, requesting publish
        $failingFile = $this->createFailingUploadedFile('replacement_fail.pdf', 'Disk write failure: simulated I/O error');

        try {
            $this->service->updateDraftVersion($doc, $draftVersion, $this->company, [
                'file' => $failingFile,
                'change_summary' => 'Attempted replacement that fails storage',
                'publish' => true,
            ]);
            $this->fail('Expected DomainException when replacement file cannot be stored.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Disk write failure', $e->getMessage());
        }

        // 1. Existing draft version in database remains completely intact
        $draftVersion->refresh();
        $this->assertSame($initialPath, $draftVersion->file_path);
        $this->assertSame('draft_orig.pdf', $draftVersion->file_name);
        $this->assertSame(PodDocumentUploadStatus::READY, $draftVersion->upload_status);
        $this->assertSame(PodDocumentVersionStatus::DRAFT, $draftVersion->status);
        $this->assertNull($draftVersion->published_at);
        $this->assertNull($draftVersion->failure_message);

        // 2. Existing draft file still exists on private storage (no cleanup scheduled or executed)
        Storage::disk('local')->assertExists($initialPath);

        // 3. Document status remains DRAFT (no publish transition occurred)
        $doc->refresh();
        $this->assertSame(PodDocumentStatus::DRAFT, $doc->status);
    }

    public function test_failed_storage_during_create_draft_version_preserves_existing_draft_attachment(): void
    {
        Storage::fake('local');

        $initialFile = UploadedFile::fake()->create('draft_v1_orig.pdf', 120, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Draft Guidelines',
            'type' => PodDocumentType::GUIDELINE,
            'file' => $initialFile,
            'publish' => false,
        ]);

        $existingDraft = $doc->draftVersion();
        $initialPath = $existingDraft->file_path;
        $this->assertNotNull($initialPath);
        Storage::disk('local')->assertExists($initialPath);

        // Attempt createDraftVersion (which updates the existing draft under lock) with a failing file
        $failingFile = $this->createFailingUploadedFile('failing_draft_v2.pdf', 'Disk write failure');

        try {
            $this->service->createDraftVersion($doc, $this->company, [
                'file' => $failingFile,
                'change_summary' => 'Should fail storage',
                'publish' => true,
            ]);
            $this->fail('Expected DomainException when replacement file cannot be stored in createDraftVersion.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Disk write failure', $e->getMessage());
        }

        // 1. Existing draft version row in database remains untouched
        $existingDraft->refresh();
        $this->assertSame($initialPath, $existingDraft->file_path);
        $this->assertSame('draft_v1_orig.pdf', $existingDraft->file_name);
        $this->assertSame(PodDocumentUploadStatus::READY, $existingDraft->upload_status);
        $this->assertSame(PodDocumentVersionStatus::DRAFT, $existingDraft->status);
        $this->assertNull($existingDraft->published_at);

        // 2. Old file still exists on private storage
        Storage::disk('local')->assertExists($initialPath);

        // 3. No extra versions created and document not published
        $doc->refresh();
        $this->assertSame(1, $doc->versions()->count());
        $this->assertSame(PodDocumentStatus::DRAFT, $doc->status);
    }

    public function test_failed_storage_during_update_document_preserves_existing_draft_attachment(): void
    {
        Storage::fake('local');

        $initialFile = UploadedFile::fake()->create('doc_draft_orig.pdf', 100, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Initial Document Title',
            'description' => 'Initial description',
            'type' => PodDocumentType::PROCESS,
            'file' => $initialFile,
            'publish' => false,
        ]);

        $draftVersion = $doc->draftVersion();
        $initialPath = $draftVersion->file_path;
        $this->assertNotNull($initialPath);
        Storage::disk('local')->assertExists($initialPath);

        // Attempt updateDocument with new title, description, and a failing replacement file
        $failingFile = $this->createFailingUploadedFile('failing_doc_replacement.pdf', 'Disk full');

        try {
            $this->service->updateDocument($doc, $this->company, [
                'title' => 'Renamed Title That Should Rollback',
                'description' => 'Updated description that should rollback',
                'file' => $failingFile,
                'change_summary' => 'Replacement should fail',
                'publish' => true,
            ]);
            $this->fail('Expected DomainException when replacement file cannot be stored in updateDocument.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Disk full', $e->getMessage());
        }

        // 1. Parent document attributes rolled back
        $doc->refresh();
        $this->assertSame('Initial Document Title', $doc->title);
        $this->assertSame('Initial description', $doc->description);
        $this->assertSame(PodDocumentStatus::DRAFT, $doc->status);

        // 2. Draft version attributes unchanged
        $draftVersion->refresh();
        $this->assertSame($initialPath, $draftVersion->file_path);
        $this->assertSame('doc_draft_orig.pdf', $draftVersion->file_name);
        $this->assertSame(PodDocumentUploadStatus::READY, $draftVersion->upload_status);
        $this->assertSame(PodDocumentVersionStatus::DRAFT, $draftVersion->status);
        $this->assertNull($draftVersion->published_at);

        // 3. Storage file preserved
        Storage::disk('local')->assertExists($initialPath);
    }

    public function test_failed_storage_returning_false_rejects_update_and_preserves_draft_attachment(): void
    {
        Storage::fake('local');

        $initialFile = UploadedFile::fake()->create('draft_orig_false.pdf', 100, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Storage Failure Test',
            'type' => PodDocumentType::ONBOARDING,
            'file' => $initialFile,
            'publish' => false,
        ]);

        $draftVersion = $doc->draftVersion();
        $initialPath = $draftVersion->file_path;
        $this->assertNotNull($initialPath);
        Storage::disk('local')->assertExists($initialPath);

        // Simulate storage returning false (e.g. underlying driver failure)
        $nullFile = $this->createNullStorageUploadedFile('null_storage_replacement.pdf');

        try {
            $this->service->updateDraftVersion($doc, $draftVersion, $this->company, [
                'file' => $nullFile,
                'change_summary' => 'Attempted replacement with null storage result',
            ]);
            $this->fail('Expected DomainException when store() returns false.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Failed to store file on private storage', $e->getMessage());
        }

        // Verify attachment and disk file remain intact
        $draftVersion->refresh();
        $this->assertSame($initialPath, $draftVersion->file_path);
        Storage::disk('local')->assertExists($initialPath);
    }

    public function test_failed_storage_during_create_document_rejects_creation(): void
    {
        Storage::fake('local');

        $failingFile = $this->createFailingUploadedFile('initial_fail.pdf', 'Disk write failure on create');

        try {
            $this->service->createDocument($this->pod, $this->company, [
                'title' => 'Should Not Be Created',
                'type' => PodDocumentType::ONBOARDING,
                'file' => $failingFile,
            ]);
            $this->fail('Expected DomainException on failed upload during createDocument.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Disk write failure on create', $e->getMessage());
        }

        // Verify no document was created in the database
        $this->assertFalse(PodDocument::where('pod_id', $this->pod->id)->where('title', 'Should Not Be Created')->exists());
    }

    public function test_failed_storage_during_create_draft_version_on_published_document_does_not_create_draft(): void
    {
        Storage::fake('local');

        $publishedFile = UploadedFile::fake()->create('published_v1.pdf', 100, 'application/pdf');
        $doc = $this->service->createDocument($this->pod, $this->company, [
            'title' => 'Published Document',
            'type' => PodDocumentType::POLICY,
            'file' => $publishedFile,
            'publish' => true,
        ]);

        $v1 = $doc->publishedVersion();
        $v1Path = $v1->file_path;
        $this->assertNotNull($v1Path);
        Storage::disk('local')->assertExists($v1Path);

        // Attempt to create a new draft version on published document with a failing file
        $failingFile = $this->createFailingUploadedFile('draft_v2_fail.pdf', 'I/O failure');

        try {
            $this->service->createDraftVersion($doc, $this->company, [
                'file' => $failingFile,
                'change_summary' => 'V2 draft attempt',
            ]);
            $this->fail('Expected DomainException when new draft version file upload fails.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('I/O failure', $e->getMessage());
        }

        // 1. Document still has only 1 version (the published V1)
        $doc->refresh();
        $this->assertSame(1, $doc->versions()->count());
        $this->assertNull($doc->draftVersion());

        // 2. Published V1 is untouched
        $v1->refresh();
        $this->assertSame(PodDocumentVersionStatus::PUBLISHED, $v1->status);
        $this->assertSame($v1Path, $v1->file_path);
        Storage::disk('local')->assertExists($v1Path);
    }
}
