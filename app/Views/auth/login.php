<main class="auth-wrap">
    <form class="auth-card" id="login" autocomplete="off">
        <h1>로그인</h1>
        <p>가입한 아이디로 들어와 주세요.</p>
        <label class="auth-field">아이디
            <input name="user_id" required autocomplete="username"/>
        </label>
        <label class="auth-field">비밀번호
            <input name="password" type="password" required autocomplete="current-password"/>
        </label>
        <button class="auth-btn" type="submit">로그인</button>
        <p class="auth-msg" id="msg"></p>
        <p class="auth-foot">처음이신가요? <a href="/signup">회원가입</a></p>
    </form>
</main>