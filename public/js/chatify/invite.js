

// invite button
$(".messenger-infoView-btns .invite-user").on("click", function() {    
    app_modal({
        name: "invite",
    });
    console.log(messenger.split("_")[1]);
    console.log(data);

});
// invite modal [invite button]
document.querySelector(".app-modal[data-name=invite] .sendInvite").addEventListener("click", function() {
    let selectedItem = document.getElementById("selectItem").selectedOptions[0];
    let url = selectedItem.getAttribute('data-url');
    let id = messenger.split("_")[1]
    let data = {
        user_id: id,
        _token: '{{ csrf_token() }}'
    };
    let method = url.includes('pods') ? 'POST' : 'GET';
    if (method === 'GET') {
        url += `?user_id=${encodeURIComponent(id)}`;
    }
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: method === 'POST' ? JSON.stringify(data) : undefined
    })
    .then(response => {
        app_modal({
            show: false,
            name: "invite",
          });
    })
});
// invite modal [cancel button]
$(".app-modal[data-name=invite]")
.find(".app-modal-footer .cancel")
.on("click", function() {
    app_modal({
    show: false,
    name: "invite",
    });
});
        