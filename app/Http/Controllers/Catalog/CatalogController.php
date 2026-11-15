<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Services\Catalog\CatalogEntryInUse;
use App\Services\Catalog\CatalogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Shared administrator CRUD for reference catalogs (Industries, Skills, Professional Roles).
 * Subclasses provide the model and labels; authorization comes from each model's policy (index,
 * create/edit pages, delete) and from the FormRequests (store/update).
 *
 * Views: catalog.index (list), catalog.form (modal fragment) and catalog.page (the same form as a
 * full page, used for non-AJAX requests and after a validation error). Each receives $catalog.
 */
abstract class CatalogController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(protected CatalogService $catalog)
    {
    }

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /**
     * Route prefix and translated labels used by the shared views.
     *
     * @return array{route:string, param:string, title:string, singular:string, description:string, icon:string,
     *     field_label:string, placeholder:string, search:string, empty:string, created:string, updated:string,
     *     deleted:string, in_use:string, usage_label:string, usage_help:string, add:string, edit:string}
     */
    abstract protected function labels(): array;

    protected function listing(Request $request)
    {
        $model = $this->model();
        $this->authorize('viewAny', $model);

        $search = $model::normalizeLabel((string) $request->query('search', ''));
        $tagged = $this->tagsIndustries();
        $industryId = $tagged ? (int) $request->query('industry', 0) : 0;

        $links = $this->links();
        $entries = $model::query()->withUsageCounts()->search($search)
            ->when($tagged, fn ($q) => $q->with(['industries' => fn ($i) => $i->alphabetical()]))
            ->when($links, fn ($q) => $q->withCount(array_keys($links)))
            ->when($industryId > 0, fn ($q) => $q->whereHas('industries', fn ($i) => $i->whereKey($industryId)))
            ->alphabetical()
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('catalog.index', [
            'catalog' => $this->catalog(),
            'entries' => $entries,
            'search' => $search,
            'industryFilter' => $industryId,
            'industries' => $tagged ? Industry::query()->alphabetical()->get(['id', 'name']) : collect(),
        ]);
    }

    protected function form(Request $request, ?Model $entry = null)
    {
        $entry ? $this->authorize('update', $entry) : $this->authorize('create', $this->model());

        $tagged = $this->tagsIndustries();
        $data = [
            'catalog' => $this->catalog(),
            'entry' => $entry,
            'industries' => $tagged ? Industry::query()->alphabetical()->get(['id', 'name']) : collect(),
            'selectedIndustries' => $tagged && $entry ? $entry->industries()->pluck('industries.id')->all() : [],
        ];

        return view($request->ajax() ? 'catalog.form' : 'catalog.page', $data);
    }

    protected function created(string $label, ?array $industryIds = null)
    {
        $this->catalog->create($this->model(), $label, $industryIds);

        return redirect()->route($this->labels()['route'] . '.index')->with('success', $this->labels()['created']);
    }

    protected function renamed(Model $entry, string $label, ?array $industryIds = null)
    {
        $this->catalog->rename($entry, $label, $industryIds);

        return redirect()->route($this->labels()['route'] . '.index')->with('success', $this->labels()['updated']);
    }

    protected function deleted(Request $request, Model $entry)
    {
        $this->authorize('delete', $entry);
        $labels = $this->labels();

        try {
            $this->catalog->delete($entry, $labels['in_use']);
        } catch (CatalogEntryInUse $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'usage' => $e->usage], 409);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $labels['deleted']]);
        }

        return redirect()->route($labels['route'] . '.index')->with('success', $labels['deleted']);
    }

    /**
     * Classification links shown in their own column instead of "Used by" (an industry's skills and
     * roles): relation => translated label.
     *
     * @return array<string, string>
     */
    private function links(): array
    {
        if ($this->tagsIndustries()) {
            return [];
        }

        return array_intersect_key(
            ['skills' => __('skills'), 'jobTitles' => __('roles')],
            array_flip(($this->model())::tagRelations())
        );
    }

    private function tagsIndustries(): bool
    {
        return in_array('industries', ($this->model())::tagRelations(), true);
    }

    private function catalog(): array
    {
        return $this->labels() + ['field' => ($this->model())::catalogColumn(), 'tagged' => $this->tagsIndustries(), 'links' => $this->links()];
    }
}
