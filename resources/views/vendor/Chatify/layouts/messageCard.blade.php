@php
$profile_img  =\App\Models\Utility::get_file(config('chatify.attachments.folder'));    
@endphp


{{-- -------------------- The default card (white) -------------------- --}}
@if($viewType == 'default')
    @if($from_id != $to_id)
    <div class="d-flex align-items-center">

        <img class="theme-avtar m-1" style="width:20px; height:20px;"
                    @if ($from_avatar)
                        src="{{ \App\Models\Utility::get_file('users-avatar/'. $from_avatar) }}"
                    @else
                        avatar="{{ $from_name }}"
                    @endif
                alt="{{$from_name}}">

        <div class="message-card ms-1" data-id="{{ $id }}">
            <p>{!! ($message == null && $attachment != null && @$attachment[2] != 'file') 
                ? $attachment[1] 
                : nl2br(
                    '<i><b>'.$from_name.'</b></i><br>' . $message) 
                !!}
                <sub title="{{ $fullTime }}">{{ $time }}</sub>
                {{-- If attachment is a file --}}
                @if(@$attachment[2] == 'file')
                <a href="{{ route(config('chatify.attachments.download_route_name'),['fileName'=>$attachment[0]]) }}" style="color: #595959;" class="file-download">
                    <span class="fas fa-file"></span> {{$attachment[1]}}</a>
                @endif
            </p>
        </div>
    </div>
    {{-- If attachment is an image --}}
    @if(@$attachment[2] == 'image')
    <div>
        <div class="message-card">
            <div class="image-file chat-image" style="width: 250px; height: 150px;background-image: url('{{ asset($profile_img.'/'.$attachment[0])}}')">
            </div>
        </div>
    </div>
    @endif
    @endif
@endif

{{-- -------------------- Sender card (owner) -------------------- --}}
@if($viewType == 'sender')
    <div class="message-card mc-sender" data-id="{{ $id }}">
        <p>{!! ($message == null && $attachment != null && @$attachment[2] != 'file') ? $attachment[1] : nl2br($message) !!}
            <sub title="{{ $fullTime }}" class="message-time">
                <span class="fas fa-{{ $seen > 0 ? 'check-double' : 'check' }} seen"></span> {{ $time }}</sub>
                {{-- If attachment is a file --}}
            @if(@$attachment[2] == 'file')
            <a href="{{ route(config('chatify.attachments.download_route_name'),['fileName'=>$attachment[0]]) }}" class="file-download">
                <span class="fas fa-file"></span> {{$attachment[1]}}</a>
            @endif
        </p>
    </div>
    {{-- If attachment is an image --}}
    @if(@$attachment[2] == 'image')
    <div>
        <div class="message-card mc-sender">
            <div class="image-file chat-image" style="width: 250px; height: 150px;background-image: url('{{asset( $profile_img.'/'.$attachment[0])}}')">
            </div>
        </div>
    </div>
    @endif
@endif
