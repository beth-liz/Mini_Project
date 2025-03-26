import { initializeApp } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import { getAuth ,GoogleAuthProvider ,signInWithPopup } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyDUediF8JB-s6BAMpNRh9_GMFUoA-aqlgI",
  authDomain: "login-9bb40.firebaseapp.com",
  projectId: "login-9bb40",
  storageBucket: "login-9bb40.firebasestorage.app",
  messagingSenderId: "512228834902",
  appId: "1:512228834902:web:2f528c863d990b749685f7"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
auth.languageCode = 'en';
const provider = new GoogleAuthProvider();

const googleLogin = document.getElementById("google-login-btn");
googleLogin.addEventListener("click", function() {
    signInWithPopup(auth, provider)
        .then((result) => {
            // Get user details from the result
            const credential = GoogleAuthProvider.credentialFromResult(result);
            const user = result.user;

            // Make an AJAX request to your server to handle the user data
            fetch('handle_google_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: user.email,
                    name: user.displayName,
                    google_id: user.uid
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Use the redirect URL from the server response
                    window.location.href = data.redirect;
                } else {
                    console.error("Server error:", data.message);
                    alert("Login failed. Please try again.");
                }
            })
            .catch(error => {
                console.error("Server error:", error);
                alert("Login failed. Please try again.");
            });

        }).catch((error) => {
            // Handle Errors here
            const errorCode = error.code;
            const errorMessage = error.message;
            console.error("Google Sign In Error:", errorMessage);
            alert("Google Sign In failed: " + errorMessage);
        });
});
