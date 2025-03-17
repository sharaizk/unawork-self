
@php
$setting = App\Models\Utility::getAdminPaymentSetting();
if ($setting['color']) {
    $color = $setting['color'];
}
else{
  $color = 'theme-3';
}
@endphp

@php
$logo   = \App\Models\Utility::get_file('/');
$avatar = asset(\App\Models\Utility::get_file('/logo/')) ;
$user_avatar_path = asset(\App\Models\Utility::get_file('/users-avatar/')) ;
@endphp


 {{-- -------------------- Saved Messages -------------------- --}}
@if($get == 'saved')
    <table class="messenger-list-item m-li-divider @if('user_'.Auth::user()->id == $id && $id != "0") m-list-active @endif">
        <tr data-action="0">
            {{-- Avatar side --}}
            <td>
            @if($color == "theme-1")
            <div class="avatar av-m" style="background-color: #d9efff; text-align: center;">
                <span class="far fa-bookmark" style="font-size: 22px; color: #51459d; margin-top: 12px !important;"></span>
            </div>
            @endif
             @if($color == "theme-2")
            <div class="avatar av-m" style="background-color: #d9efff; text-align: center;">
                <span class="far fa-bookmark" style="font-size: 22px; color: #1f3996; margin-top: 12px !important;"></span>
            </div>
            @endif
             @if($color == "theme-3")
            <div class="avatar av-m" style="background-color: #d9efff; text-align: center;">
                <span class="far fa-bookmark" style="font-size: 22px; color: #022D4D; margin-top: 12px !important;"></span>
            </div>
            @endif
             @if($color == "theme-4")
            <div class="avatar av-m" style="background-color: #d9efff; text-align: center;">
                <span class="far fa-bookmark" style="font-size: 22px; color: #584ed2; margin-top: 12px !important;"></span>
            </div>
            @endif

            </td>
            {{-- center side --}}
            <td>
                <p data-id="{{ 'user_'.Auth::user()->id }}">Saved Messages <span>You</span></p>
                <span>Save messages secretly</span>
            </td>
        </tr>
    </table>
@endif

{{-- -------------------- All users/group list -------------------- --}}
@if($get == 'users')
<table class="messenger-list-item @if($thread->id == $id && $id != "0") m-list-active @endif" data-contact="{{ $thread->id }}">
    <tr data-action="0">
        {{-- Avatar side --}}
        <td style="position: relative">
                 @if(!is_null($user_avatar))
                    <div class="avatar av-m"
                    style="background-image: url('{{$user_avatar}}');">
                    </div>
                @else
                    <div class="avatar av-m"
                                    style="background-image: url('{{asset($avatar.'logo-dark.png')}}');">
                                </div>
                @endif
        </td>
        {{-- center side --}}
        <td>
        <p data-id="{{ $type.'_'.$thread->id }}" data-user-id="{{ $single_participant_id }}">
            @if (is_null($user_name))
            {{ strlen($thread->subject) > 16 ? trim(substr($thread->subject,0,12)).'..' : $thread->subject }}
            @else
            {{ strlen($user_name) > 16 ? trim(substr($user_name,0,12)).'..' : $user_name }}
            @endif
            @if ($lastMessage)
            <span>{{ $lastMessage->created_at->diffForHumans() }}</span>
            @endif
        </p>

        <span>
            @if ($lastMessage)
                {{-- Last Message user indicator --}}
                {!!
                    $lastMessage->from_id == Auth::user()->id
                    ? '<span class="lastMessageIndicator">You :</span>'
                    : ''
                !!}
                {{-- Last message body --}}
                @if($lastMessage->attachment == null)
                {{
                    strlen($lastMessage->body) > 30
                    ? trim(substr($lastMessage->body, 0, 30)).'..'
                    : $lastMessage->body
                }}
                @else
                <span class="fas fa-file"></span> Attachment
                @endif
            @else
                <span class="lastMessageIndicator">Start Chat</span>
            @endif
        </span>
        {{-- New messages counter --}}
            {!! $unseenCounter > 0 ? "<b>".$unseenCounter."</b>" : '' !!}
        </td>

    </tr>
</table>
@endif

{{-- -------------------- Search Item -------------------- --}}
@if($get == 'search_item')
<table class="messenger-list-item" data-contact="{{ $user->id }}">
    <tr data-action="0">
        {{-- Avatar side --}}
            <td style="position: relative">
                {{-- @dd($subject) --}}
                 @if(!empty($user->avatar))
                    <div class="avatar av-m"
                    style="background-image:url('{{asset($user_avatar_path.$user->avatar)}}');">
                    </div>
                @else
                    {{-- @dd($thread->avatar)  --}}
                    <div class="avatar av-m"
                                    style="background-image: url('{{asset($avatar.'avatar.png')}}');">
                                </div>
                @endif
        </td>
        {{-- center side --}}
        <td>
        <p data-id="{{ $type.'_'.$thread->id }}">
            {{ strlen($user->name) > 20 ? trim(substr($user->name,0,20)).'..' : $user->name }}
        </td>

    </tr>
</table>
@endif



{{-- -------------------- Get All Members -------------------- --}}

@if($get == 'all_members')
    <table class="messenger-list-item" data-contact="{{ $user->id }}">
        <tr data-action="0">
            {{-- Avatar side --}}
            <td style="position: relative">
                @if($user->active_status)
                    <span class="activeStatus"></span>
                @endif
                @if(!empty($user->avatar))
                    <div class="avatar av-m"
                         style="background-image: url('{{asset($logo.'users-avatar/'.$user->avatar)}}');">
                    </div>
                @else
                    <div class="avatar av-m"
                         style="background-image: url('{{asset($logo.'logo/'.'logo-light.png')}}');">
                    </div>
                @endif
            </td>
            {{-- center side --}}
            <td>
                <p data-id="{{ $type.'_'.$user->id }}">
                {{ strlen($user->name) > 12 ? trim(substr($user->name,0,12)).'..' : $user->name }}
            </td>

        </tr>
    </table>
@endif

{{-- -------------------- Shared photos Item -------------------- --}}
@if($get == 'sharedPhoto')
<div class="shared-photo chat-image" style="background-image: url('{{asset($image)}}')"></div>
@endif






