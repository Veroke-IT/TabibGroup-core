document.addEventListener('DOMContentLoaded', function() {
    var passwordFields = document.querySelectorAll(
		'input[name="form-field-yyfttd"], input[name="form-field-uwdypv"], input[name="form-field-olzksk"], input[name="form-field-javdod"], input[name="form-field-uplxme"], input[name="form-field-ijendt"]'
	);
	passwordFields.forEach(function (passwordField) {
		var isRTL = getComputedStyle(passwordField).direction === "rtl";

		// Function to check if the mouse is over the icon area
		function isMouseOverIcon(event) {
			var fieldRect = passwordField.getBoundingClientRect();
			if (isRTL) {
				// Leave 32px space from the left and check if the mouse is within the icon area
				return event.clientX > fieldRect.left + 32 && event.clientX < fieldRect.left + 48;
			} else {
				// LTR: Leave 32px from the right, and make the next 16px clickable
				return event.clientX > fieldRect.right - 48 && event.clientX < fieldRect.right - 32;
			}
		}

		passwordField.addEventListener('mousemove', function(event) {
			this.style.cursor = isMouseOverIcon(event) ? 'pointer' : '';
		});

		passwordField.addEventListener('click', function(event) {
			if (isMouseOverIcon(event)) {
				if (this.type === 'password') {
					this.type = 'text';
					this.classList.add('show-password');
				} else {
					this.type = 'password';
					this.classList.remove('show-password');
				}
			}
		});
	});
});