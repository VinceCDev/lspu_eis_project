<script>
// Some browsers restore session cookies across a full browser restart
// ("continue where you left off"), which would let a closed-then-reopened
// browser keep using the old server session. sessionStorage, unlike
// cookies, is guaranteed to be cleared when the browser actually closes.
// login.js sets this marker right after a successful login, so its
// absence here means this is a genuinely new browser session riding on a
// stale restored cookie — force a real logout before anything renders.
(function () {
    if (!sessionStorage.getItem('lspu_session_active')) {
        window.location.replace('/logout');
    }
})();
</script>
