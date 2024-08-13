@php
    $company = $vacancy->company;
    $vacancy_page = route('job-vacancies.landing.show', ['vacancy' => $vacancy->id]);
@endphp
<div class="col-md-3 p-3">
    <div class="company-box">
        <div class="title-box">
                <img alt="user-image" class="img-fluid img_users_fix_size"
                @if ($company->avatar) src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $company->avatar) }}" @else src="{{ App\Helpers\FileHelper::getUrl('logo/' . 'logo-light.png') }}" @endif>
                        
                <a href="{{ $vacancy_page }}">
                    <h4>{{$vacancy->title}}</h4>
                 </a>
        </div>
        <p>
            Salary: {{ $vacancy->salary ?? '' }}
        </p>
        @if($vacancy->skills->isNotEmpty())
            <p>
                Skills:
                {{ $vacancy->skills->pluck('name')->implode(', ') }}
            </p>
        @else
            <p>
                Skills not selected for this vacation
            </p>
        @endif


        @php
            $limit = 40;
            $description = $vacancy->description ?? '';
            $truncated = \Illuminate\Support\Str::limit($description, $limit);
        @endphp

        <p>
            @if(strlen($description) > $limit)
                {!! $truncated !!} <br><a href="{{ $vacancy_page }}">Read more</a>
            @else
                {!! $truncated !!} <br> &nbsp
            @endif
        </p>
        <div class="row">
            <div class="col-lg-6">
                <a href="{{ $vacancy_page }}" class="w-100 learn-more">Learn more</a>
            </div>
        </div>
    </div>
</div>