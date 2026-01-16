document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const alertBox = document.getElementById('alertMessage');
    const btnText = document.getElementById('btnText');
    const btnLoader = document.getElementById('btnLoader');
    const submitBtn = e.target.querySelector('button[type="submit"]');
    
    // Get form data
    const formData = new FormData(e.target);
    
    // Disable button and show loader
    submitBtn.disabled = true;
    btnText.classList.add('d-none');
    btnLoader.classList.remove('d-none');
    alertBox.classList.add('d-none');
    
    try {
        const response = await fetch('auth/login_process.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            alertBox.className = 'alert alert-success';
            alertBox.textContent = data.message;
            alertBox.classList.remove('d-none');
            
            // Redirect after 1 second
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.textContent = data.message;
            alertBox.classList.remove('d-none');
            
            // Re-enable button
            submitBtn.disabled = false;
            btnText.classList.remove('d-none');
            btnLoader.classList.add('d-none');
        }
    } catch (error) {
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = 'An error occurred. Please try again.';
        alertBox.classList.remove('d-none');
        
        // Re-enable button
        submitBtn.disabled = false;
        btnText.classList.remove('d-none');
        btnLoader.classList.add('d-none');
    }
});