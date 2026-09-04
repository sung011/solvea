<main class="auth-wrap">
  <form class="auth-card" id="signup" autocomplete="off">
    <h1>회원가입</h1>
    <p>나이와 거주지를 저장해 두면, 공고를 볼 때 자격 확인이 빨라집니다.</p>

    <label class="auth-field">아이디
      <input name="user_id" maxlength="20" minlength="2" required placeholder="2~20자" autocomplete="username" />
    </label>
    <label class="auth-field">비밀번호
      <input name="password" type="password" minlength="4" required placeholder="4자 이상" autocomplete="new-password" />
    </label>
    <label class="auth-field">비밀번호 확인
      <input name="password2" type="password" minlength="4" required placeholder="한 번 더 입력" autocomplete="new-password" />
    </label>
    <label class="auth-field">이름
      <input name="name" maxlength="30" placeholder="홍길동" autocomplete="name" />
    </label>
    <div class="auth-row">
      <label class="auth-field">나이
        <input name="age" type="number" min="1" max="120" placeholder="27" />
      </label>
      <label class="auth-field">거주지
        <input name="address" maxlength="255" placeholder="광주 광산구" autocomplete="address-level2" />
      </label>
    </div>
    <button class="auth-btn" type="submit">가입하기</button>
    <p class="auth-msg" id="msg"></p>
    <p class="auth-foot">이미 계정이 있나요? <a href="/login">로그인</a></p>
  </form>
</main>
