<style>
    .invite-user-form {
        padding: 20px;
    }
</style>
<form class="invite-user-form">
        @csrf

    <div class="row">
        <div class="col-md-12 col-12 text-center">
            <span class="invite-warning"></span>
        </div>
        <div class="alert alert-success" id="success-message" style="display: none;">
                User invited successfully!
        </div>
        <div class="alert alert-warning" id="failed-message" style="display: none;">
                User invite failed!
        </div>
        <div class="col-md-12 form-group">
            {{ Form::label('email', __('Email'), ['class' => 'col-form-label']) }}
            {{ Form::text('email', null, ['class' => 'form-control', 'placeholder' => __('Enter email address')]) }}
        </div>
    </div>
    
    <div class="row">
        <div class="text-end">
            <button id="inviteUserButton" class="btn-submit btn btn-primary check-invite-members ">Invite User</button>
        </div>
    </div>
</form>