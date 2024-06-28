<div class="col-md-3">
    <div class="company-box">
        <div class="title-box">
            <!-- <img src="http://unawork.net/images/thumbnails/card-avatar.png" alt=""> -->
            <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                            @if ($company->avatar) src="{{ asset ('storage/'. $company->avatar) }}" @else avatar="{{ $company->name }}" @endif>
                        
            <h4>{{$company->name}}</h4>
        </div>
        <p>
            {{ $company->profile->country ?? '' }} {{ $company->profile->website ?? '' }}
        </p>
        <p>
            {{ $company->profile->about ?? 'about company' }}
        </p>
        <a href="#" class="learn-more">Learn more</a>
    </div>
</div>