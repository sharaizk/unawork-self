<div class="navbar-wrapper">
    <div class="navbar-content">
        <div class="list-group list-group-flush" style="top:30px">
            <div class="list-group list-group-flush" id="useradd-sidenav">
                <a href="{{ route('pods.users', $podId=$podId) }}"
                    class="list-group-item list-group-item-action border-0 active">
                    <span class="dash-micon"><i class="ti ti-home"></i></span>
                    <span class="dash-mtext">{{ __('Users') }}</span>
                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                </a>
            </div>
        </div>       
    </div>
</div>