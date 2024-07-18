<div class="col-md-3">
    <div class="company-box">
        <div class="title-box">
            <!-- <img src="http://unawork.net/images/thumbnails/card-avatar.png" alt=""> -->
            <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                            @if ($company->avatar) src="{{ asset ('storage/'. $company->avatar) }}" @else avatar="{{ $company->name }}" @endif>
                        
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
                <a href="#" class="w-100 learn-more">Learn more</a>
            </div>
            <div class="col lg-6">
                @if (Auth::user())
                    @if (!in_array($company->id, Auth::user()->workspace()->pluck('workspaces.id')->toArray())  )
                        <a href="{{ route('company.join', ['workspace' => $company->id]) }}" class="w-100 learn-more">Join</a>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>