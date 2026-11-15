{{-- Step panel actions. Params: $key; uses $steps. Both buttons save the whole form (drafts allowed). --}}
@php $isLast = $key === end($steps); @endphp
<div class="card-footer co-panel__footer">
    @if ($key !== $steps[0])
        <button type="button" class="btn btn-light" data-co-prev>{{ __('Back') }}</button>
    @endif
    <span class="flex-grow-1"></span>
    <button type="submit" class="btn btn-outline-primary" name="intent" value="draft" data-co-submit="{{ $key }}">
        <i class="ti ti-device-floppy"></i> {{ $isLast ? __('Save') : __('Save draft') }}
    </button>
    @unless ($isLast)
        <button type="submit" class="btn btn-primary" name="intent" value="continue" data-co-submit="{{ $key }}">
            {{ __('Save and continue') }} <i class="ti ti-arrow-right"></i>
        </button>
    @endunless
</div>
