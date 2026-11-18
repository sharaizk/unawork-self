<?php

namespace App\Policies;

use App\Models\Pod;
use App\Models\PodDocument;
use App\Models\PodDocumentVersion;
use App\Models\User;

/**
 * Policy for PodDocument models (UNA-020).
 *
 * Enforces business user management boundaries and applicant access limits.
 */
class PodDocumentPolicy
{
    private PodPolicy $podPolicy;

    public function __construct(PodPolicy $podPolicy)
    {
        $this->podPolicy = $podPolicy;
    }

    public function view(User $user, PodDocument $document): bool
    {
        $pod = $document->pod;
        if (!$pod) {
            return false;
        }

        return $this->podPolicy->viewDocument($user, $pod, $document);
    }

    public function create(User $user, Pod $pod): bool
    {
        return $this->podPolicy->manageDocuments($user, $pod);
    }

    public function update(User $user, PodDocument $document): bool
    {
        $pod = $document->pod;
        if (!$pod) {
            return false;
        }

        return $this->podPolicy->manageDocuments($user, $pod);
    }

    public function delete(User $user, PodDocument $document): bool
    {
        $pod = $document->pod;
        if (!$pod) {
            return false;
        }

        return $this->podPolicy->manageDocuments($user, $pod);
    }

    public function viewVersion(User $user, PodDocument $document, PodDocumentVersion $version): bool
    {
        $pod = $document->pod;
        if (!$pod) {
            return false;
        }

        return $this->podPolicy->viewDocumentVersion($user, $pod, $document, $version);
    }

    public function downloadVersion(User $user, PodDocument $document, PodDocumentVersion $version): bool
    {
        $pod = $document->pod;
        if (!$pod) {
            return false;
        }

        return $this->podPolicy->downloadDocumentVersion($user, $pod, $document, $version);
    }
}
