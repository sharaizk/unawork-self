<div class="col-md-3">
    <div class="company-box">
        <div class="title-box">
            <!-- <img src="http://unawork.net/images/thumbnails/card-avatar.png" alt=""> -->
            <img alt="user-image" class="img-fluid img_users_fix_size"
            @if ($company->logo) src="{{ App\Helpers\FileHelper::getUrl('logo/' . $company->logo) }}" @else src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" @endif>
                        
            <h4>{{$company->name}}</h4>
        </div>
        <p>
            {{ $company->country ?? '' }} {{ $company->website ?? '' }}
        </p>
        <p>
            {{ $company->about ?? 'about company' }}
        </p>
        <div class="row">
            <div class="col lg-6">
                <a href="{{ route('landing.company.show', ['company' => $company->id]) }}" class="w-100 learn-more">Learn more</a>
            </div>
            <div class="col lg-6">
                    @php
                        $user = Auth::user();
                        $workspace = $user->workspace()->where('workspace_id', $company->id)->first();
                        $status = $workspace ? $workspace->pivot->status : null;
                    @endphp
                @if ($user)
                    @if (is_null($workspace))
                        <a href="{{ route('company.join', ['workspace' => $company->id]) }}" class="w-100 learn-more">Join</a>
                    @elseif ($status === "active")
                        <div class="learn-more"><span class="small">You are member of this company</span></div>
                    @elseif ($status ===  "workspace_pending")
                        <div class="learn-more"><span class="small">You have sended request to join this company</span></div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>