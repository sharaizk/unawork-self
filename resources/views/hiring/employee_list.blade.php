@foreach($employees as $employee)
    <div class="col-12 col-md-6 col-xl-3">
        @include('hiring.employeecard', ['employee' => $employee])
    </div>
@endforeach
