<?php

namespace App\Http\Controllers;

use App\Enums\PodDocument\PodDocumentStatus;
use App\Enums\PodDocument\PodDocumentType;
use App\Enums\PodDocument\PodDocumentUploadStatus;
use App\Enums\PodDocument\PodDocumentVersionStatus;
use App\Exceptions\PodDocument\CannotDeleteDocumentWithHistoryException;
use App\Exceptions\PodDocument\CannotDeletePublishedVersionException;
use App\Exceptions\PodDocument\CannotModifyImmutableVersionException;
use App\Http\Requests\PodDocument\ReorderPodDocumentsRequest;
use App\Http\Requests\PodDocument\StorePodDocumentRequest;
use App\Http\Requests\PodDocument\StorePodDocumentVersionRequest;
use App\Http\Requests\PodDocument\UpdatePodDocumentRequest;
use App\Http\Requests\PodDocument\UpdatePodDocumentVersionRequest;
use App\Models\Pod;
use App\Models\PodDocument;
use App\Models\PodDocumentVersion;
use App\Services\PodDocument\PodDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Controller for POD Knowledge Base documents, onboarding materials,
 * private file uploads, and version history (UNA-020, UNA-021).
 */
class PodDocumentController extends Controller
{
    public function __construct(
        private PodDocumentService $documentService,
    ) {
    }

    /**
     * List knowledge base documents and onboarding materials for a POD.
     */
    public function index(Request $request, Pod $pod)
    {
        $this->authorize('viewDocuments', $pod);

        $isManager = $request->user()->can('manageDocuments', $pod);
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');

        if ($isManager) {
            $query = $pod->documents()->ordered()->with(['versions', 'creator']);

            if ($typeFilter && in_array($typeFilter, PodDocumentType::values(), true)) {
                $query->where('type', $typeFilter);
            }
            if ($statusFilter && in_array($statusFilter, PodDocumentStatus::values(), true)) {
                $query->where('status', $statusFilter);
            }

            $documents = $query->paginate(15)->withQueryString();
        } else {
            // Associated applicants and members see published documents with ready published version only
            $query = $pod->publishedDocuments()
                ->ordered()
                ->whereHas('versions', function ($q) {
                    $q->where('status', PodDocumentVersionStatus::PUBLISHED->value)
                        ->where('upload_status', PodDocumentUploadStatus::READY->value);
                })
                ->with([
                    'versions' => fn ($q) => $q->where('status', PodDocumentVersionStatus::PUBLISHED->value)
                        ->where('upload_status', PodDocumentUploadStatus::READY->value),
                    'creator',
                ]);

            if ($typeFilter && in_array($typeFilter, PodDocumentType::values(), true)) {
                $query->where('type', $typeFilter);
            }

            $documents = $query->paginate(15)->withQueryString();
        }

        $currentWorkspace = $pod->workspace ?? $request->user()->currentWorkspace;
        $types = PodDocumentType::cases();
        $statuses = PodDocumentStatus::cases();

        return view('pod.documents.index', compact('pod', 'documents', 'isManager', 'currentWorkspace', 'types', 'statuses', 'typeFilter', 'statusFilter'));
    }

    /**
     * Reorder onboarding material documents for a POD.
     */
    public function reorder(ReorderPodDocumentsRequest $request, Pod $pod)
    {
        try {
            $this->documentService->reorderDocuments($pod, $request->input('ordered_ids'));
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['is_success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()
                ->route('pods.documents.index', $pod)
                ->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['is_success' => true, 'message' => __('Material reordered successfully.')]);
        }

        return redirect()
            ->route('pods.documents.index', $pod)
            ->with('success', __('Material reordered successfully.'));
    }

    /**
     * Show form to create a new Knowledge Base document / onboarding material.
     */
    public function create(Pod $pod)
    {
        $this->authorize('manageDocuments', $pod);

        $currentWorkspace = $pod->workspace ?? auth()->user()->currentWorkspace;
        $types = PodDocumentType::cases();
        $allowedExtensions = config('pod_materials.allowed_extensions', ['pdf', 'doc', 'docx']);
        $maxFileSizeMb = round(config('pod_materials.max_file_size_kb', 20480) / 1024, 1);

        return view('pod.documents.create', compact('pod', 'types', 'currentWorkspace', 'allowedExtensions', 'maxFileSizeMb'));
    }

    /**
     * Store a new Knowledge Base document / onboarding material.
     */
    public function store(StorePodDocumentRequest $request, Pod $pod)
    {
        try {
            $document = $this->documentService->createDocument($pod, $request->user(), $request->validated());
        } catch (CannotModifyImmutableVersionException|\DomainException $e) {
            return redirect()
                ->route('pods.documents.create', $pod)
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pods.documents.show', [$pod, $document])
            ->with('success', __('Knowledge Base document created successfully.'));
    }

    /**
     * Show a document and its current published/active version.
     */
    public function show(Request $request, Pod $pod, PodDocument $document)
    {
        $this->authorize('viewDocument', [$pod, $document]);

        $isManager = $request->user()->can('manageDocuments', $pod);

        if ($isManager) {
            $version = $document->publishedVersion() ?? $document->latestVersion();
            $versions = $document->versions()->with('creator')->get();
        } else {
            $version = $document->publishedVersion();
            abort_if(!$version || !$version->isUploadReady(), 404);
            $versions = collect([$version]);
        }

        $currentWorkspace = $pod->workspace ?? $request->user()->currentWorkspace;

        return view('pod.documents.show', compact('pod', 'document', 'version', 'versions', 'isManager', 'currentWorkspace'));
    }

    /**
     * Download the active file of a document.
     */
    public function download(Request $request, Pod $pod, PodDocument $document)
    {
        $this->authorize('viewDocument', [$pod, $document]);

        $isManager = $request->user()->can('manageDocuments', $pod);
        $version = $isManager ? ($document->publishedVersion() ?? $document->latestVersion()) : $document->publishedVersion();

        abort_if(!$version || !$version->isFile(), 404, __('No file attached to this material.'));

        return $this->downloadVersion($request, $pod, $document, $version);
    }

    /**
     * Download a specific version file through protected private storage.
     */
    public function downloadVersion(Request $request, Pod $pod, PodDocument $document, PodDocumentVersion $version)
    {
        $this->authorize('viewDocumentVersion', [$pod, $document, $version]);

        abort_if((int) $document->pod_id !== (int) $pod->id || (int) $version->pod_document_id !== (int) $document->id, 404);
        abort_if(!$version->isFile(), 404, __('No file attached to this version.'));

        $isManager = $request->user()->can('manageDocuments', $pod);
        if (!$isManager) {
            abort_if(!$document->isPublished() || !$version->isPublished() || !$version->isUploadReady(), 403);
        }

        try {
            $disk = $this->documentService->getPrivateDisk();
        } catch (\DomainException $e) {
            abort(403, $e->getMessage());
        }

        if (!Storage::disk($disk)->exists($version->file_path)) {
            abort(404, __('The requested file was not found on private storage.'));
        }

        $downloadName = $version->file_name ?: basename($version->file_path);
        return Storage::disk($disk)->download($version->file_path, $downloadName);
    }

    /**
     * Show form to edit document metadata.
     */
    public function edit(Pod $pod, PodDocument $document)
    {
        $this->authorize('manageDocuments', $pod);
        abort_if((int) $document->pod_id !== (int) $pod->id, 404);

        $currentWorkspace = $pod->workspace ?? auth()->user()->currentWorkspace;
        $types = PodDocumentType::cases();
        $latestVersion = $document->latestVersion();
        $allowedExtensions = config('pod_materials.allowed_extensions', ['pdf', 'doc', 'docx']);
        $maxFileSizeMb = round(config('pod_materials.max_file_size_kb', 20480) / 1024, 1);

        return view('pod.documents.edit', compact('pod', 'document', 'types', 'latestVersion', 'currentWorkspace', 'allowedExtensions', 'maxFileSizeMb'));
    }

    /**
     * Update document metadata and optionally content.
     */
    public function update(UpdatePodDocumentRequest $request, Pod $pod, PodDocument $document)
    {
        abort_if((int) $document->pod_id !== (int) $pod->id, 404);

        try {
            $this->documentService->updateDocument($document, $request->user(), $request->validated());
        } catch (CannotModifyImmutableVersionException|\DomainException $e) {
            return redirect()
                ->route('pods.documents.show', [$pod, $document])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pods.documents.show', [$pod, $document])
            ->with('success', __('Document updated successfully.'));
    }

    /**
     * Show form to create a new version for an existing document.
     */
    public function createVersion(Pod $pod, PodDocument $document)
    {
        $this->authorize('manageDocuments', $pod);
        abort_if((int) $document->pod_id !== (int) $pod->id, 404);

        $nextVersionNumber = ($document->versions()->max('version_number') ?? 0) + 1;
        $currentWorkspace = $pod->workspace ?? auth()->user()->currentWorkspace;
        $publishedVersion = $document->publishedVersion();
        $allowedExtensions = config('pod_materials.allowed_extensions', ['pdf', 'doc', 'docx']);
        $maxFileSizeMb = round(config('pod_materials.max_file_size_kb', 20480) / 1024, 1);

        return view('pod.documents.create_version', compact('pod', 'document', 'nextVersionNumber', 'publishedVersion', 'currentWorkspace', 'allowedExtensions', 'maxFileSizeMb'));
    }

    /**
     * Store a new version for an existing document.
     */
    public function storeVersion(StorePodDocumentVersionRequest $request, Pod $pod, PodDocument $document)
    {
        abort_if((int) $document->pod_id !== (int) $pod->id, 404);

        try {
            $this->documentService->createDraftVersion($document, $request->user(), $request->validated());
        } catch (CannotModifyImmutableVersionException|\DomainException $e) {
            return redirect()
                ->route('pods.documents.versions.create', [$pod, $document])
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pods.documents.show', [$pod, $document])
            ->with('success', __('New document version created.'));
    }

    /**
     * View a specific version of a document.
     */
    public function showVersion(Request $request, Pod $pod, PodDocument $document, PodDocumentVersion $version)
    {
        $this->authorize('viewDocumentVersion', [$pod, $document, $version]);

        $isManager = $request->user()->can('manageDocuments', $pod);
        $currentWorkspace = $pod->workspace ?? $request->user()->currentWorkspace;

        return view('pod.documents.show_version', compact('pod', 'document', 'version', 'isManager', 'currentWorkspace'));
    }

    /**
     * Show form to edit a draft version.
     */
    public function editVersion(Pod $pod, PodDocument $document, PodDocumentVersion $version)
    {
        $this->authorize('manageDocuments', $pod);
        abort_if((int) $document->pod_id !== (int) $pod->id || (int) $version->pod_document_id !== (int) $document->id, 404);

        if ($version->isImmutable()) {
            return redirect()
                ->route('pods.documents.show', [$pod, $document])
                ->with('error', __('Published and archived versions cannot be edited. Create a new draft version instead.'));
        }

        $currentWorkspace = $pod->workspace ?? auth()->user()->currentWorkspace;
        $allowedExtensions = config('pod_materials.allowed_extensions', ['pdf', 'doc', 'docx']);
        $maxFileSizeMb = round(config('pod_materials.max_file_size_kb', 20480) / 1024, 1);

        return view('pod.documents.edit_version', compact('pod', 'document', 'version', 'currentWorkspace', 'allowedExtensions', 'maxFileSizeMb'));
    }

    /**
     * Update an existing draft version.
     */
    public function updateVersion(UpdatePodDocumentVersionRequest $request, Pod $pod, PodDocument $document, PodDocumentVersion $version)
    {
        abort_if((int) $document->pod_id !== (int) $pod->id || (int) $version->pod_document_id !== (int) $document->id, 404);

        try {
            $this->documentService->updateDraftVersion($document, $version, $request->user(), $request->validated());
        } catch (CannotModifyImmutableVersionException|\DomainException $e) {
            return redirect()
                ->route('pods.documents.show', [$pod, $document])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pods.documents.show', [$pod, $document])
            ->with('success', __('Document version updated successfully.'));
    }

    /**
     * Publish a draft version.
     */
    public function publishVersion(Request $request, Pod $pod, PodDocument $document, PodDocumentVersion $version)
    {
        $this->authorize('manageDocuments', $pod);
        abort_if((int) $document->pod_id !== (int) $pod->id || (int) $version->pod_document_id !== (int) $document->id, 404);

        try {
            $this->documentService->publishVersion($document, $version, $request->user());
        } catch (CannotModifyImmutableVersionException|\DomainException $e) {
            return redirect()
                ->route('pods.documents.show', [$pod, $document])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pods.documents.show', [$pod, $document])
            ->with('success', __('Document version published successfully.'));
    }

    /**
     * Delete a draft version.
     */
    public function destroyVersion(Request $request, Pod $pod, PodDocument $document, PodDocumentVersion $version)
    {
        $this->authorize('manageDocuments', $pod);
        abort_if((int) $document->pod_id !== (int) $pod->id || (int) $version->pod_document_id !== (int) $document->id, 404);

        try {
            $this->documentService->deleteDraftVersion($document, $version);
        } catch (CannotDeletePublishedVersionException|\DomainException $e) {
            return redirect()
                ->route('pods.documents.show', [$pod, $document])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pods.documents.show', [$pod, $document])
            ->with('success', __('Draft version deleted successfully.'));
    }

    /**
     * Archive a document.
     */
    public function archive(Request $request, Pod $pod, PodDocument $document)
    {
        $this->authorize('manageDocuments', $pod);
        abort_if((int) $document->pod_id !== (int) $pod->id, 404);

        $this->documentService->archiveDocument($document, $request->user());

        return redirect()
            ->route('pods.documents.index', $pod)
            ->with('success', __('Document archived successfully.'));
    }

    /**
     * Delete an un-published draft document.
     */
    public function destroy(Request $request, Pod $pod, PodDocument $document)
    {
        $this->authorize('manageDocuments', $pod);
        abort_if((int) $document->pod_id !== (int) $pod->id, 404);

        try {
            $this->documentService->deleteDocument($document);
        } catch (CannotDeleteDocumentWithHistoryException $e) {
            return redirect()
                ->route('pods.documents.show', [$pod, $document])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('pods.documents.index', $pod)
            ->with('success', __('Draft document deleted successfully.'));
    }
}
