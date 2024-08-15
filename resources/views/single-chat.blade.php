
<link rel='stylesheet' href='https://unpkg.com/nprogress@0.2.0/nprogress.css'/>
<link href="{{ asset('css/chatify/style.css') }}" rel="stylesheet" />
<link href="{{ asset('css/chatify/dark.mode.css') }}" rel="stylesheet" />
<style>
    /* .m-body {
        height: 150px; 
        overflow-y: auto;
        position: relative;
    }

    .messages {
        padding-bottom: 10px;
    } */
    .messenger-messagingView .m-body {
        padding-top: 15px;
        /* height: calc(100% - 90px); */
        height: calc(100% - 100px) !important; 
        /* height: 350px !important; */
        overflow-y: auto;
    }
    .messenger-messagingView {
        background: #ffffff;
    }
    .show-infoSide {
        /* display: none; */
    }
    .m-header-right .add-to-favorite {
        display: none;
    }
    .messenger-sendCard {
        background: #ffffff;
        border-top: 1px solid #b8b8b8
    }
    .m-header a, .m-header a:hover, .m-header a:focus {
        text-decoration: none;
        color: #000;
    }
    .m-header-messaging {
        background: #ffffff;
    }
    .messenger-messagingView {
        border-top: 0px;
        border-bottom: 0px;
    }
    .messenger-sendCard span::before {
        width: 50px;
        height: 50px;
    }

    .messenger-sendCard svg:active {
        transform: scale(.9);
    }
    .m-send {
        color: #000;
    }
    .messenger-infoView {
        background: #fff;
        border: 1px solid #b8b8b8;
    }
    .messenger-infoView>p {
        color: #000;
    }
    .messenger-infoView-btns {
        display: none;
    }
    .messenger-sendCard .upload-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        cursor: pointer;
    }
    .messenger-sendCard .fa-paperclip {
        font-size: 18px; 
        margin-right: 5px;
        margin-top: 10px;
        color: #bdcbd6;
    }
    .messenger-sendCard .fas:hover {
        cursor: pointer;
    }
</style>
<div class="card rounded-12">
        <div class="messenger rounded overflow-hidden mt-4 px-4" style="height: 500px;">

            {{-- ----------------------Messaging side---------------------- --}}
            <div class="messenger-messagingView">
                {{-- header title [conversation name] amd buttons --}}
                <div class="m-header m-header-messaging">
                    <nav class="d-flex a;align-items-center justify-content-between">
                        {{-- header back button, avatar and user name --}}
                        <div style="display: flex;">
                            <a href="#" class="show-listView"><i class="fas fa-arrow-left"></i> </a>
                            @if (!empty(Auth::user()->avatar))
                                <div class="avatar av-s header-avatar"
                                    style="margin: 0px 10px; margin-top: -5px; margin-bottom: -5px;background-image: url('{{ ('users/avatar' . Auth::user()->avatar) }}'); background-size: cover;">
                                </div>
                            @else
                                <div class="avatar av-s header-avatar"
                                    style="margin: 0px 10px; margin-top: -5px; margin-bottom: -5px;background-image: url('{{ ('users/avatar' . 'avatar.png') }}');">
                                </div>
                            @endif
                            <a href="#" class="user-name">{{ config('chatify.name') }}</a>
                        </div>
                        {{-- header buttons --}}
                        <nav class="m-header-right">
                            <a href="#" class="add-to-favorite my-lg-1 my-xl-1 mx-lg-1 mx-xl-1"><i
                                    class="fas fa-star"></i></a>
                            <a href="#" class="show-infoSide my-lg-1 my-xl-1 mx-lg-1 mx-xl-2"><i
                                    class="fas fa-info-circle"></i></a>
                        </nav>
                    </nav>
                </div>
                {{-- Internet connection --}}
                <div class="internet-connection">
                    <span class="ic-connected">{{ __('Connected') }}</span>
                    <span class="ic-connecting">{{ __('Connecting...') }}</span>
                    <span class="ic-noInternet">{{ __('Please add pusher settings for using messenger.') }}</span>
                </div>
                {{-- Messaging area --}}
                <div class="m-body app-scroll">
                    <div class="messages">
                        <p class="message-hint" style="margin-top: calc(30% - 126.2px);">
                            <span>{{ __('Please select a chat to start messaging') }}</span></p>
                    </div>
                    {{-- Typing indicator --}}
                    <div class="typing-indicator">
                        <div class="message-card typing">
                            <p>
                                <span class="typing-dots">
                                    <span class="dot dot-1"></span>
                                    <span class="dot dot-2"></span>
                                    <span class="dot dot-3"></span>
                                </span>
                            </p>
                        </div>
                    </div>
                    {{-- Send Message Form --}}
                    @include('Chatify::layouts.sendForm')
                </div>
            </div>
            {{-- ---------------------- Info side ---------------------- --}}
            <div class="messenger-infoView app-scroll text-center">
                {{-- nav actions --}}
                <nav class="text-left">
                    <a href="#"><i class="fas fa-times"></i></a>
                </nav>
                {!! view('Chatify::layouts.info')->render() !!}
            </div>
        </div>
    </div>


<script src="{{ asset('js/chatify/app.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        getContacts();
        messenger = 'user_{{$threadId}}';
        IDinfo(messenger.split("_")[1], messenger.split("_")[0]);

        $(".messenger-infoView nav a , .show-infoSide").on("click", function() {
            event.preventDefault();
            $(".messenger-infoView").toggle();
        });

        $("#message-form .m-send").on("keyup", (e) => {
            // if enter key pressed.
            if (e.which == 13 || e.keyCode == 13) {
            // if shift + enter key pressed, do nothing (new line).
            // if only enter key pressed, send message.
            if (!e.shiftKey) {
                triggered = isTyping(false);
                sendMessage();
            }
            }
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function() {
            const ChatName = "Project Chat";
            $(".m-header-messaging .user-name").html(ChatName);
            $(".messenger-infoView .info-name").html(ChatName);
            $(".m-header-messaging .m-header-right .add-to-favorite").css('display', 'none');
            $(".m-header-messaging .justify-content-between .show-listView").css('display', 'none');
            $(".m-header-messaging .justify-content-between a").on('click', function(event) {
                event.preventDefault();
            });


        }, 1000); 
    });
</script>