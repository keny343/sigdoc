<?php
/**
 * Closes sig-content / sig-main / body. Optional $sigdoc_extra_scripts.
 */
?>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var btn = document.getElementById('sigMenuBtn');
  var side = document.getElementById('sigSidebar');
  var back = document.getElementById('sigBackdrop');
  function closeNav() {
    if (side) side.classList.remove('open');
    if (back) back.classList.remove('show');
  }
  function openNav() {
    if (side) side.classList.add('open');
    if (back) back.classList.add('show');
  }
  if (btn) btn.addEventListener('click', function () {
    if (side && side.classList.contains('open')) closeNav();
    else openNav();
  });
  if (back) back.addEventListener('click', closeNav);
})();
</script>
<?php if (!empty($sigdoc_extra_scripts)) echo $sigdoc_extra_scripts; ?>
</body>
</html>
