{{-- user info and avatar --}}

@php
$logo  =\App\Models\Utility::get_file(config('chatify.user_avatar.folder')); 
@endphp

@if(!empty(\Auth::user()->avatar))
<div class="avatar av-l "style="background-image: url('{{asset( $logo.'/'.Auth::user()->avatar)}}');"></div>   
@else
    <div class="avatar av-l"
         style="background-image: url('{{ asset($logo.'/'.'avatar.png')}}');">
    </div>
@endif
<p class="info-name">{{ config('chatify.name') }}</p>
<div class="messenger-infoView-btns">
    {{-- <a href="#" class="default"><i class="fas fa-camera"></i> default</a> --}}
    <a href="#" class="danger delete-conversation"><i class="fas fa-trash-alt"></i> Delete Conversation</a>

    {{-- <a href="#" class="default"><i class="fas fa-camera"></i> default</a> --}}
    <a href="#" class="default invite-user"><I class="fas fa-user-plus"></I> Invite User</a>
</div>
{{-- shared photos --}}
<!-- <div class="messenger-infoView-shared">
    <p class="messenger-title">shared photos</p>
    <div class="shared-photos-list"></div>
</div> -->

<div class="messenger-infoView-shared">
    <p class="messenger-title">Users in thread</p>
    <div class="users-list"></div>
</div>
<!-- 
<style>

.theme-avtar {
    border-radius: 50%;
    font-size: 16px;
    max-width: 20px;
    max-height: 20px;
    background: #dcdaeb;
    color: #51459d;
    object-fit: cover;
    
}
</style> -->

