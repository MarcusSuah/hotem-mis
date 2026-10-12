<?php

namespace App\Http\Controllers\Admin\Outlets;

use App\Enums\Permission;
use App\Enums\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Outlets\CatalogRequest;
use App\Models\AdditionalService;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Currency\CurrencyConverter;
use App\Services\Media\ImageStorage;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Restaurant menu and the catalogue of bookable guest services.
 */
class CatalogController extends Controller
{
    public function __construct(
        private CurrencyConverter $converter,
        private ImageStorage $images,
    ) {}

    public function index(): Response
    {
        Gate::authorize(Permission::ServicesManage->value);

        return Inertia::render('admin/outlets/catalog', [
            'categories' => MenuCategory::query()->withCount('items')->orderBy('display_order')->orderBy('name')->get(['id', 'name', 'slug', 'display_order', 'is_active']),
            'menuItems' => MenuItem::query()->with('category:id,name')->orderBy('menu_category_id')->orderBy('display_order')->orderBy('name')->get()
                ->map(fn (MenuItem $item) => [
                    ...$item->only(['id', 'slug', 'menu_category_id', 'name', 'description', 'is_available', 'is_featured', 'display_order']),
                    'category' => $item->category->name,
                    'price' => Money::ofMinor($item->price_minor, $item->currency),
                    'image_url' => $item->getAttribute('image_url'),
                ]),
            'services' => AdditionalService::query()->orderBy('display_order')->orderBy('name')->get()
                ->map(fn (AdditionalService $service) => [
                    ...$service->only(['id', 'slug', 'name', 'short_description', 'description', 'unit', 'is_active', 'is_featured', 'display_order']),
                    'category' => $service->category->value,
                    'category_label' => $service->category->label(),
                    'price' => Money::ofMinor($service->price_minor, $service->currency),
                    'image_url' => $service->getAttribute('image_url'),
                ]),
            'serviceCategories' => ServiceCategory::options(),
            'currency' => $this->converter->baseCurrency()->value,
        ]);
    }

    public function storeCategory(CatalogRequest $request): RedirectResponse
    {
        MenuCategory::create($request->catalogAttributes($this->currency()));

        return $this->saved(__('Menu category created.'));
    }

    public function updateCategory(CatalogRequest $request, MenuCategory $category): RedirectResponse
    {
        $category->update($request->catalogAttributes($this->currency()));

        return $this->saved(__('Menu category updated.'));
    }

    public function storeItem(CatalogRequest $request): RedirectResponse
    {
        $this->persistWithImage(new MenuItem, $request, 'menu');

        return $this->saved(__('Menu item created.'));
    }

    public function updateItem(CatalogRequest $request, MenuItem $item): RedirectResponse
    {
        $this->persistWithImage($item, $request, 'menu');

        return $this->saved(__('Menu item updated.'));
    }

    public function destroyItem(MenuItem $item): RedirectResponse
    {
        Gate::authorize(Permission::ServicesManage->value);

        $item->delete();

        return $this->saved(__('Menu item removed.'));
    }

    public function storeService(CatalogRequest $request): RedirectResponse
    {
        $this->persistWithImage(new AdditionalService, $request, 'services');

        return $this->saved(__('Service created.'));
    }

    public function updateService(CatalogRequest $request, AdditionalService $service): RedirectResponse
    {
        $this->persistWithImage($service, $request, 'services');

        return $this->saved(__('Service updated.'));
    }

    public function destroyService(AdditionalService $service): RedirectResponse
    {
        Gate::authorize(Permission::ServicesManage->value);

        $service->delete();

        return $this->saved(__('Service removed.'));
    }

    private function persistWithImage(Model $model, CatalogRequest $request, string $directory): void
    {
        $model->fill($request->catalogAttributes($this->currency()));
        $previous = $model->getAttribute('image_path');

        if ($request->hasFile('image')) {
            $model->setAttribute('image_path', $this->images->store($request->file('image'), $directory));
        }

        $model->save();

        if ($request->hasFile('image')) {
            $this->images->delete(is_string($previous) ? $previous : null);
        }
    }

    private function currency(): string
    {
        return $this->converter->baseCurrency()->value;
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
