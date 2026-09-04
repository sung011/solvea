<?php if (empty($isAuthPage)): ?>
<p class="disclaimer">자격 확정이 아닙니다. 최종 심사는 운영기관이 합니다.</p>
<?php if (($bodyClass ?? '') === 'page-favorites'): ?>
<script src="/js/favorites.js?v=20260904f"></script>
<?php else: ?>
<script src="/js/app.js?v=20260904i"></script>
<?php endif; ?>
<?php else: ?>
<script src="/js/auth.js"></script>
<?php endif; ?>
</body>
</html>
