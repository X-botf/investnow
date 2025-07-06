var pusher = new Pusher(pusherAppKey, {
    encrypted: true,
    cluster: pusherAppCluster,
});

var notificationsWrapper = $('.user-dropdown-notifications');
var notifications = notificationsWrapper.find('ul.dropdown-menu');
var userChannel = pusher.subscribe('user-notification-channel');
userChannel.bind('user-notification-event', function (data) {
    var userNotificationCounter = $('.user-notification-counter');
    var count = parseInt(userNotificationCounter.text()) + 1;
    userNotificationCounter.text(count);


    playSound();

});

function playSound() {

    $.get(soundUrl, function (data) {
        var audio = new Audio(data);
        audio.play();
        audio.muted = false;

    });
}
