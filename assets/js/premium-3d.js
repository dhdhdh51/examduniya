'use strict';
/* ============================================================
   Exam Duniya — Premium 3D Enhancement Engine
   ------------------------------------------------------------
   Pure progressive enhancement. If this script fails to load or
   the device is weak, the site stays exactly as before (flat,
   fast, fully functional). No backend / DOM-content changes.

   Capabilities are detected up-front:
     - prefers-reduced-motion  -> disable all motion fx
     - low-end device (cores / memory / mobile) -> reduce fx
     - no WebGL -> CSS fallback hero, no Three.js download
   Three.js is loaded LAZILY and ONLY on pages that contain a
   [data-3d-hero] element.
   ============================================================ */
(function () {

  var docEl = document.documentElement;
  var prefersReduced = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---- Device capability scoring -------------------------------------
  var isMobile = /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  var cores    = navigator.hardwareConcurrency || (isMobile ? 4 : 8);
  var memory   = navigator.deviceMemory || (isMobile ? 4 : 8);
  var lowEnd   = cores <= 4 || memory <= 3;

  function hasWebGL() {
    try {
      var c = document.createElement('canvas');
      return !!(window.WebGLRenderingContext &&
        (c.getContext('webgl') || c.getContext('experimental-webgl')));
    } catch (e) { return false; }
  }
  var webgl = hasWebGL();

  // Master switch: enable fx only when motion is allowed.
  var fxEnabled = !prefersReduced;

  if (fxEnabled) {
    document.body.classList.add('fx-on', 'fx-page-enter');
  }

  /* ============================================================
     1) Scroll-reveal via IntersectionObserver
     ============================================================ */
  function initReveal() {
    if (!fxEnabled || !('IntersectionObserver' in window)) return;
    var els = document.querySelectorAll('[data-reveal]');
    if (!els.length) return;

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    els.forEach(function (el) { io.observe(el); });
  }

  // Auto-tag common sections for reveal if author didn't add it.
  function autoTagReveal() {
    if (!fxEnabled) return;
    var candidates = document.querySelectorAll(
      'main section, .main-content section, .stat-card, .card'
    );
    candidates.forEach(function (el) {
      if (!el.hasAttribute('data-reveal')) el.setAttribute('data-reveal', '');
    });
  }

  /* ============================================================
     2) 3D tilt + shine on cards (desktop pointer only)
     ============================================================ */
  function initTilt() {
    if (!fxEnabled || lowEnd) return;
    // Only on devices with a fine pointer (mouse) to keep touch smooth.
    if (window.matchMedia && !window.matchMedia('(pointer: fine)').matches) return;

    var nodes = document.querySelectorAll('[data-tilt], .fx-tilt');
    nodes.forEach(function (el) {
      var maxTilt = parseFloat(el.getAttribute('data-tilt-max')) || 8;

      // inject shine layer if missing
      if (!el.querySelector('.fx-shine')) {
        if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
        var shine = document.createElement('span');
        shine.className = 'fx-shine';
        el.appendChild(shine);
      }

      function onMove(e) {
        var r = el.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width;
        var py = (e.clientY - r.top) / r.height;
        var rx = (0.5 - py) * maxTilt * 2;
        var ry = (px - 0.5) * maxTilt * 2;
        el.style.transform =
          'perspective(800px) rotateX(' + rx.toFixed(2) + 'deg) rotateY(' +
          ry.toFixed(2) + 'deg) translateZ(6px)';
        el.style.setProperty('--mx', (px * 100) + '%');
        el.style.setProperty('--my', (py * 100) + '%');
      }
      function reset() { el.style.transform = ''; }

      el.addEventListener('mousemove', onMove);
      el.addEventListener('mouseleave', reset);
    });
  }

  /* ============================================================
     3) Mouse-follow parallax (desktop) / device-tilt (mobile)
        for elements marked [data-parallax] (e.g. hero blobs).
     ============================================================ */
  function initParallax() {
    if (!fxEnabled || lowEnd) return;
    var layers = document.querySelectorAll('[data-parallax]');
    if (!layers.length) return;

    var tx = 0, ty = 0, cx = 0, cy = 0, raf = null;

    function loop() {
      cx += (tx - cx) * 0.08;
      cy += (ty - cy) * 0.08;
      layers.forEach(function (el) {
        var depth = parseFloat(el.getAttribute('data-parallax')) || 0.2;
        el.style.transform =
          'translate3d(' + (cx * depth).toFixed(2) + 'px,' +
          (cy * depth).toFixed(2) + 'px,0)';
      });
      raf = (Math.abs(tx - cx) > 0.1 || Math.abs(ty - cy) > 0.1)
        ? requestAnimationFrame(loop) : null;
    }
    function kick() { if (!raf) raf = requestAnimationFrame(loop); }

    if (window.matchMedia && window.matchMedia('(pointer: fine)').matches) {
      window.addEventListener('mousemove', function (e) {
        tx = (e.clientX - window.innerWidth / 2) / 18;
        ty = (e.clientY - window.innerHeight / 2) / 18;
        kick();
      });
    } else if (window.DeviceOrientationEvent) {
      // Touch-friendly: subtle movement from device orientation.
      window.addEventListener('deviceorientation', function (e) {
        tx = (e.gamma || 0) / 2;   // left/right
        ty = (e.beta || 0) / 4;    // front/back
        kick();
      });
    }
  }

  /* ============================================================
     4) Lightweight particle background (2D canvas, capped)
     ============================================================ */
  function initParticles() {
    if (!fxEnabled || lowEnd) return;            // skip on weak devices
    if (document.querySelector('.fx-particles')) return;

    var canvas = document.createElement('canvas');
    canvas.className = 'fx-particles';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.insertBefore(canvas, document.body.firstChild);

    var ctx = canvas.getContext('2d');
    var W, H, parts = [];
    var COUNT = isMobile ? 26 : 54;              // capped for performance

    function resize() {
      W = canvas.width = window.innerWidth;
      H = canvas.height = window.innerHeight;
    }
    function seed() {
      parts = [];
      for (var i = 0; i < COUNT; i++) {
        parts.push({
          x: Math.random() * W, y: Math.random() * H,
          vx: (Math.random() - 0.5) * 0.25,
          vy: (Math.random() - 0.5) * 0.25,
          r: Math.random() * 1.8 + 0.6
        });
      }
    }
    function draw() {
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < parts.length; i++) {
        var p = parts[i];
        p.x += p.vx; p.y += p.vy;
        if (p.x < 0 || p.x > W) p.vx *= -1;
        if (p.y < 0 || p.y > H) p.vy *= -1;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(37,99,235,0.5)';
        ctx.fill();
        // link nearby particles (skip on mobile to save CPU)
        if (!isMobile) {
          for (var j = i + 1; j < parts.length; j++) {
            var q = parts[j], dx = p.x - q.x, dy = p.y - q.y;
            var d = dx * dx + dy * dy;
            if (d < 12000) {
              ctx.beginPath();
              ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y);
              ctx.strokeStyle = 'rgba(124,58,237,' + (0.12 * (1 - d / 12000)) + ')';
              ctx.stroke();
            }
          }
        }
      }
      raf = requestAnimationFrame(draw);
    }

    var raf = null, visible = true;
    document.addEventListener('visibilitychange', function () {
      visible = !document.hidden;
      if (visible && !raf) draw();
      if (!visible && raf) { cancelAnimationFrame(raf); raf = null; }
    });

    resize(); seed();
    window.addEventListener('resize', function () { resize(); seed(); });
    requestAnimationFrame(function () { canvas.classList.add('is-ready'); draw(); });
  }

  /* ============================================================
     5) Lazy 3D hero with Three.js (ONLY if [data-3d-hero] exists)
     ============================================================ */
  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src; s.async = true;
      s.onload = resolve; s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function initHero3D() {
    var host = document.querySelector('[data-3d-hero]');
    if (!host) return;                 // page has no 3D hero -> no download
    if (!fxEnabled || !webgl || lowEnd) return;  // CSS fallback stays

    // Defer until the hero is near the viewport (it usually is at top).
    loadScript('https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js')
      .then(function () { buildHeroScene(host); })
      .catch(function () { /* silent: CSS hero remains */ });
  }

  function buildHeroScene(host) {
    if (typeof THREE === 'undefined') return;

    var canvas = document.createElement('canvas');
    canvas.className = 'fx-hero-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    host.insertBefore(canvas, host.firstChild);

    var w = host.clientWidth, h = host.clientHeight || 480;
    var renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(w, h);

    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(55, w / h, 0.1, 100);
    camera.position.z = 9;

    // Lights
    scene.add(new THREE.AmbientLight(0xffffff, 0.7));
    var key = new THREE.DirectionalLight(0x88aaff, 1.1);
    key.position.set(5, 6, 8); scene.add(key);
    var rim = new THREE.PointLight(0x7c3aed, 1.2, 50);
    rim.position.set(-6, -3, 6); scene.add(rim);

    // Niche-relevant floating objects: knowledge "nodes" + rings
    // (abstract, light geometry — small download, fast render)
    var group = new THREE.Group();
    var palette = [0x2563eb, 0x7c3aed, 0x0ea5e9, 0x22c55e];
    var COUNT = isMobile ? 7 : 13;
    var shapes = [];
    for (var i = 0; i < COUNT; i++) {
      var pick = Math.random();
      var geo;
      if (pick < 0.34)      geo = new THREE.IcosahedronGeometry(0.6, 0);
      else if (pick < 0.67) geo = new THREE.TorusGeometry(0.5, 0.18, 12, 28);
      else                  geo = new THREE.OctahedronGeometry(0.6, 0);

      var mat = new THREE.MeshStandardMaterial({
        color: palette[i % palette.length],
        metalness: 0.35, roughness: 0.3,
        transparent: true, opacity: 0.9
      });
      var mesh = new THREE.Mesh(geo, mat);
      mesh.position.set((Math.random() - 0.5) * 12, (Math.random() - 0.5) * 6, (Math.random() - 0.5) * 4);
      mesh.userData.spin = (Math.random() - 0.5) * 0.01;
      mesh.userData.floatY = Math.random() * Math.PI * 2;
      shapes.push(mesh); group.add(mesh);
    }
    scene.add(group);

    // Mouse parallax for the whole group
    var mx = 0, my = 0;
    if (window.matchMedia && window.matchMedia('(pointer: fine)').matches) {
      window.addEventListener('mousemove', function (e) {
        mx = (e.clientX / window.innerWidth - 0.5);
        my = (e.clientY / window.innerHeight - 0.5);
      });
    }

    var running = true;
    document.addEventListener('visibilitychange', function () {
      running = !document.hidden;
      if (running) animate();
    });

    var t = 0;
    function animate() {
      if (!running) return;
      t += 0.01;
      shapes.forEach(function (m, i) {
        m.rotation.x += m.userData.spin;
        m.rotation.y += m.userData.spin * 0.8;
        m.position.y += Math.sin(t + m.userData.floatY) * 0.0035;
      });
      group.rotation.y += (mx * 0.6 - group.rotation.y) * 0.05;
      group.rotation.x += (my * 0.4 - group.rotation.x) * 0.05;
      renderer.render(scene, camera);
      requestAnimationFrame(animate);
    }

    requestAnimationFrame(function () {
      canvas.classList.add('is-ready');
      animate();
    });

    window.addEventListener('resize', function () {
      w = host.clientWidth; h = host.clientHeight || 480;
      camera.aspect = w / h; camera.updateProjectionMatrix();
      renderer.setSize(w, h);
    });
  }

  /* ============================================================
     6) Soft page transitions (does NOT block real navigation)
     ============================================================ */
  function initPageTransitions() {
    if (!fxEnabled) return;

    var bar = document.createElement('div');
    bar.className = 'fx-progress';
    document.body.appendChild(bar);

    function start() {
      bar.style.opacity = '1';
      bar.style.width = '70%';
    }
    // finish on full load / pageshow
    window.addEventListener('pageshow', function () {
      bar.style.width = '100%';
      setTimeout(function () { bar.style.opacity = '0'; bar.style.width = '0'; }, 250);
    });

    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a');
      if (!a) return;
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#') return;
      if (a.target === '_blank' || a.hasAttribute('download')) return;
      if (a.getAttribute('rel') === 'external') return;
      // only same-origin internal links
      if (a.host && a.host !== window.location.host) return;
      if (/^(mailto:|tel:|javascript:)/i.test(href)) return;
      // let modified clicks behave normally
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

      start();
      document.body.classList.add('fx-page-leave');
      // Navigation proceeds naturally; class is just a fade hint.
    });
  }

  /* ============================================================
     7) Theme toggle (light <-> dark). Default = light.
     ============================================================ */
  function initThemeToggle() {
    var btn = document.querySelector('[data-theme-toggle]');
    if (!btn) return;

    function setIcon() {
      var dark = docEl.getAttribute('data-theme') === 'dark';
      btn.innerHTML = dark
        ? '<i class="fa-solid fa-sun"></i>'
        : '<i class="fa-solid fa-moon"></i>';
      btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    }
    setIcon();

    btn.addEventListener('click', function () {
      var dark = docEl.getAttribute('data-theme') === 'dark';
      if (dark) { docEl.removeAttribute('data-theme'); localStorage.setItem('ed-theme', 'light'); }
      else      { docEl.setAttribute('data-theme', 'dark'); localStorage.setItem('ed-theme', 'dark'); }
      setIcon();
    });
  }

  /* ============================================================
     Boot
     ============================================================ */
  function boot() {
    autoTagReveal();
    initReveal();
    initTilt();
    initParallax();
    initParticles();
    initHero3D();
    initPageTransitions();
    initThemeToggle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
