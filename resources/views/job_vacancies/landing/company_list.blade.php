<div class="row-master">
    @forelse($usersWithVacancies as $userWithVacancies)
        @include('company.landing.company_card', ['company' => $userWithVacancies])
    @empty
        <p class="text-center">No companies found.</p>
    @endforelse
</div>
