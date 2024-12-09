document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form[id^="accf-form"]');
    if (!forms.length) return;

    forms.forEach(form => {
        const formId = form.id.replace('accf-form-', '');

        // Function to validate the form
        function validateForm(form) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (field.disabled || field.offsetParent === null) {
                    return; // Skip disabled or hidden fields
                }
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            return isValid;
        }

        // Function to update conditional groups visibility
        function updateConditionalGroups() {
            const groups = form.querySelectorAll('.conditional-group');
            groups.forEach(group => {
                const triggerName = group.getAttribute('data-trigger-name');
                const triggerValue = group.getAttribute('data-trigger-value');
                const triggerField = form.querySelector(`input[name="${triggerName}"]:checked`);
                if (triggerField) {
                    const isVisible = triggerField.value === triggerValue;
                    group.style.display = isVisible ? 'block' : 'none';
                    group.querySelectorAll('[required]').forEach(field => {
                        if (!isVisible) {
                            field.removeAttribute('required');
                            field.disabled = true;
                        } else {
                            field.setAttribute('required', 'required');
                            field.disabled = false;
                        }
                    });
                }
            });
        }

        // Update range input values dynamically
        form.querySelectorAll('input[type="range"]').forEach(range => {
            const output = document.getElementById(`${range.id}-output`);
            if (output) {
                output.textContent = range.value;
                range.addEventListener('input', function () {
                    output.textContent = this.value;
                });
            }
        });

        // Update conditional groups when radio buttons change
        form.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', updateConditionalGroups);
        });

        updateConditionalGroups();

        // Handle form submission
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            console.log('Submitting form:', this.id);

            // Validate the form
            if (!validateForm(this)) {
                alert('Veuillez remplir tous les champs requis.');
                return;
            }

            const formData = new FormData(this);
            const fileInputs = this.querySelectorAll('input[type="file"]');

            // File validation and appending to FormData
            const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf']; // Adjust as needed
            const maxSize = 5 * 1024 * 1024; // 5MB

            let isValidFiles = true;

            fileInputs.forEach(fileInput => {
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    if (!allowedTypes.includes(file.type)) {
                        alert(`File type not allowed: ${file.name}`);
                        isValidFiles = false;
                        return;
                    }
                    if (file.size > maxSize) {
                        alert(`File size exceeds limit: ${file.name}`);
                        isValidFiles = false;
                        return;
                    }
                    formData.append(fileInput.name, file);
                }
            });

            if (!isValidFiles) return;

            const submitButton = this.querySelector('button[type="submit"]');
            const responseContainerId = `form-response-${formId}`;
            let responseContainer = document.getElementById(responseContainerId);

            // Create response container dynamically if not found
            if (!responseContainer) {
                responseContainer = document.createElement('div');
                responseContainer.id = responseContainerId;
                this.parentNode.appendChild(responseContainer);
            }

            submitButton.disabled = true;

            fetch(accf_ajax.ajax_url, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        responseContainer.innerHTML = `<p>${response.data.message}</p>`;
                    } else {
                        responseContainer.innerHTML = `<p>${response.data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    responseContainer.innerHTML =
                        '<p>Une erreur est survenue lors de l\'envoi du formulaire.</p>';
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
        });
        jQuery(document).ready(function($) {
            form.addEventListener('change', function() {
                var turkin = this.id;
                $(".accf-form-class:not(#" + turkin + ") input").prop('disabled', true);
            });
        });
    });
});