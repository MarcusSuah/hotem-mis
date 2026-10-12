<?php

namespace App\Http\Controllers\Admin\Website\Concerns;

use App\Http\Requests\Admin\Website\WebsiteContentRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Media\ImageStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Shared save, image and audit handling for website content controllers.
 */
trait ManagesWebsiteContent
{
    /**
     * Fill and save content, replacing its image only after the new one is stored.
     */
    protected function persistContent(Model $model, WebsiteContentRequest $request, ?string $imageColumn = null, string $directory = 'website'): void
    {
        $creating = ! $model->exists;
        $model->fill($request->contentAttributes());
        $previous = $imageColumn ? $model->getAttribute($imageColumn) : null;

        if ($imageColumn !== null && $request->hasFile('image')) {
            $model->setAttribute($imageColumn, app(ImageStorage::class)->store($request->file('image'), $directory));
        }

        $model->save();

        if ($imageColumn !== null && $request->hasFile('image')) {
            app(ImageStorage::class)->delete(is_string($previous) ? $previous : null);
        }

        $audit = app(AuditLogger::class);
        $action = 'website.'.$request->kind().($creating ? '.created' : '.updated');

        if ($creating) {
            $audit->record($action, $model, newValues: ['title' => $model->getAttribute('title')]);
        } else {
            $audit->recordChanges($action, $model);
        }
    }

    protected function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
