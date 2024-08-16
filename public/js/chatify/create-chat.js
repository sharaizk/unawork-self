// new chat button
$(".newChatBtn").on("click", function() {    
    app_modal({
        name: "create",
    });

    $('.m-header-create-chat .selectUsersItem').select2({
        placeholder: "Select Users",
        allowClear: true,
        width: '100%'
    });
    $('#selectUsersItem').on('select2:open', function() {
        $('.select2-dropdown').css('z-index', 9999); // Вказуємо потрібний z-index
    });
});

// new chat modal [cancel button]
$(".app-modal[data-name=create]")
.find(".app-modal-footer .cancel")
.on("click", function() {
    app_modal({
    show: false,
    name: "create",
    });
});