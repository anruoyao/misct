$(document).ready(function () {
  $("#loginForm").on("submit", function (event) {
    event.preventDefault();
    var formData = new FormData($("#loginForm")[0]);
    $.ajax({
      url: `${domainUrl}loginForm`,
      type: "POST",
      data: formData,
      dataType: "json",
      contentType: false,
      cache: false,
      processData: false,
      success: function (response) {
        if (response.status) {
          window.location.href = `${domainUrl}index`;
        } else {
          iziToast.show({
            title: "Error",
            message: response.message,
            color: "red",
            position: toastPosition,
            transitionIn: "fadeInUp",
            transitionOut: "fadeOutDown",
            timeout: 3000,
            animateInside: false,
            iconUrl: `${domainUrl}asset/img/x.svg`,
          });
        }
      },
      error: function (err) {
        console.log(err);
      },
    });
  });

  $("#forgotPasswordForm").on("submit", function (event) {
    event.preventDefault();
    var formData = new FormData(this);

    var newPassword = $("#new_password").val();
    var confirmPassword = $("#confirm_password").val();

    if (newPassword !== confirmPassword) {
      showErrorToast("Passwords do not match!");
      return;
    }

    $.ajax({
      url: `${domainUrl}forgotPasswordForm`,
      type: "POST",
      data: formData,
      dataType: "json",
      contentType: false,
      processData: false,
      success: function (response) {
        if (response.status) {
          $("#forgotPasswordModal").modal("hide");
          resetForm("#forgotPasswordForm");
          resetForm("#loginForm");
          iziToast.show({
            title: "Updated",
            message: "Login successfull.",
            color: "green",
            position: toastPosition,
            transitionIn: "fadeInUp",
            transitionOut: "fadeOutDown",
            timeout: 3000,
            animateInside: true,
            iconUrl: `${domainUrl}asset/img/check-circle.svg`,
          });
        } else {
          iziToast.show({
            title: "Error",
            message: response.message,
            color: "red",
            position: toastPosition,
            transitionIn: "fadeInUp",
            transitionOut: "fadeOutDown",
            timeout: 3000,
            animateInside: false,
            iconUrl: `${domainUrl}asset/img/x.svg`,
          });
        }
      },
      error: function (err) {
        console.log(err);
      },
    });
  });

  function togglePasswordVisibility(
    eyeSelector,
    eyeOffSelector,
    inputSelector
  ) {
    const eye = document.querySelector(eyeSelector);
    const eyeOff = document.querySelector(eyeOffSelector);
    const passwordField = document.querySelector(inputSelector);

    if (!eye || !eyeOff || !passwordField) return; // safeguard

    eye.addEventListener("click", () => {
      eye.style.display = "none";
      eyeOff.style.display = "block";
      passwordField.type = "text";
    });

    eyeOff.addEventListener("click", () => {
      eyeOff.style.display = "none";
      eye.style.display = "block";
      passwordField.type = "password";
    });
  }

  // Call the function for each password field
  togglePasswordVisibility(
    ".feather-eye",
    ".feather-eye-off",
    "input[type=password]"
  );
  togglePasswordVisibility(".eye2", ".eye-off2", "input#new_password");
  togglePasswordVisibility(".eye3", ".eye-off3", "input#confirm_password");
  togglePasswordVisibility(".eye4", ".eye-off4", "input#database_password");
});
