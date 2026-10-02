function showAppointments(type) {
    document.getElementById('current-appointments').style.display = type === 'current' ? 'block' : 'none';
    document.getElementById('past-appointments').style.display = type === 'past' ? 'block' : 'none';
    document.querySelectorAll('.af-item').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.af-item[onclick="showAppointments('${type}')"]`).classList.add('active');
}

function fetchAppointments(page, type) {
    var xhr = new XMLHttpRequest();
    var wrapper = document.getElementById('appointment-listing');
    var appointmentContainer = document.getElementById(type + '-appointments');
    var loaderOverlay = document.getElementById('tg-loader-overlay');

    // Show Loader Overlay & Scroll to Top
    loaderOverlay.style.display = 'flex';
    window.scrollTo({ top: wrapper.offsetTop, behavior: 'smooth' });

    xhr.open('POST', window.appointmentsObj, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            loaderOverlay.style.display = 'none';

            if (xhr.status === 200) {
                appointmentContainer.innerHTML = xhr.responseText;
            } else {
                appointmentContainer.innerHTML = '<p style="color: red;">حدث خطأ أثناء تحميل المواعيد. يرجى المحاولة مرة أخرى.</p>';
            }
        }
    };

    // Correct the way data is sent
    xhr.send('action=load_appointments&page=' + encodeURIComponent(page) + '&type=' + encodeURIComponent(type));
}

// Handle "Go Back" buttons
document.querySelector('.ab-go-back').addEventListener('click', function () {
    if (document.referrer) {
        window.location.href = document.referrer;
    } else {
        window.location.href = '/offers';
    }
});