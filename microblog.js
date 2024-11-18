document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("microblog-form");
    const responseContainer = document.getElementById("upload-response");

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch(ajax_object.ajax_url, {
                method: "POST",
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.redirect) {
                            // Redirect based on server response
                            window.location.href = data.redirect;
                        } else {
                            responseContainer.innerHTML = "Microblog submitted successfully!";
                            form.reset(); // Reset form after successful submission
                        }
                    } else {
                        responseContainer.innerHTML = "Error: " + (data.data || "An unknown error occurred.");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    responseContainer.innerHTML = "Error: An unexpected error occurred.";
                });
        });
    } else {
        console.error("Error: Form with ID 'microblog-form' not found.");
    }
});
