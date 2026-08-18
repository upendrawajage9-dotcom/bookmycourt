function showForm(type) {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const loginTab = document.getElementById('loginTab');
    const signupTab = document.getElementById('signupTab');

    // hide any previous messages when switching
    hideMessages();

    if (type === 'login') {
        loginForm.style.display = 'block';
        signupForm.style.display = 'none';
        loginTab.classList.add('active');
        signupTab.classList.remove('active');
    } else {
        loginForm.style.display = 'none';
        signupForm.style.display = 'block';
        loginTab.classList.remove('active');
        signupTab.classList.add('active');
    }
}

function hideMessages() {
    const ids = ['loginError', 'loginSuccess', 'signupError', 'signupSuccess'];
    ids.forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = 'none';
            el.innerText = '';
        }
    });
}

// FRONT-END ONLY VALIDATION (no real DB yet)

function validateSignupForm() {
    hideMessages();

    const fullName = document.getElementById('fullName').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    const errorEl = document.getElementById('signupError');
    const successEl = document.getElementById('signupSuccess');

    // basic checks
    if (fullName === '' || phone === '' || password === '' || confirmPassword === '') {
        errorEl.innerText = 'Please fill all required fields.';
        errorEl.style.display = 'block';
        return false;
    }

    if (phone.length < 10) {
        errorEl.innerText = 'Please enter a valid phone number (at least 10 digits).';
        errorEl.style.display = 'block';
        return false;
    }

    if (password.length < 6) {
        errorEl.innerText = 'Password should be at least 6 characters long.';
        errorEl.style.display = 'block';
        return false;
    }

    if (password !== confirmPassword) {
        errorEl.innerText = 'Password and Confirm Password do not match.';
        errorEl.style.display = 'block';
        return false;
    }

    // optional email pattern check
    if (email !== '' && !/^\S+@\S+\.\S+$/.test(email)) {
        errorEl.innerText = 'Please enter a valid email address.';
        errorEl.style.display = 'block';
        return false;
    }

    // DEMO SUCCESS (later we will send data to backend/DB)
    successEl.innerText = 'Account created successfully.';
    successEl.style.display = 'block';

    // prevent real form submission for now
    return false;
}

function validateLoginForm() {
    hideMessages();

    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value;

    const errorEl = document.getElementById('loginError');
    const successEl = document.getElementById('loginSuccess');

    if (username === '' || password === '') {
        errorEl.innerText = 'Please enter your email/phone and password.';
        errorEl.style.display = 'block';
        return false;
    }

    // DEMO ONLY: we just show success.
    // Later this will call backend to validate from database.
    successEl.innerText = 'Login successful.';
    successEl.style.display = 'block';

    // prevent real submit for now
    return false;
}
