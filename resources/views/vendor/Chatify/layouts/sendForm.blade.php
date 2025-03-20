<div class="messenger-sendCard">
    <form id="message-form" method="POST" action="{{ route('send.message') }}" enctype="multipart/form-data">
        @csrf
        <label><span class="fas fa-paperclip"></span><input disabled='disabled' type="file" class="upload-attachment" name="file" accept="image/png,image/jpeg,image/jpg,image/heic,image/webp" /></label>
        <textarea rows="3" readonly='readonly' name="message" class="m-send app-scroll" placeholder="Type a message.." style="overflow: hidden; overflow-wrap: break-word; height: 42px;"></textarea>
        <button disabled='disabled'><span class="fas fa-paper-plane"></span></button>
    </form>
</div>
<script>
document.getElementById('message-form').addEventListener('submit', function(event) {
    event.preventDefault(); 
    sendMessage();
});
</script>