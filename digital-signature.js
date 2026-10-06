(() => {
  const DATA_IMAGE = /^data:image\/png;base64,/i;

  function createController(host) {
    if (!host || host.dataset.signatureReady === '1') return null;

    const canvas = host.querySelector('[data-signature-canvas]');
    const input = host.querySelector('[data-signature-value]');
    const clear = host.querySelector('[data-signature-clear]');
    const placeholder = host.querySelector('[data-signature-placeholder]');
    const error = host.querySelector('[data-signature-error]');
    const pad = host.querySelector('[data-signature-pad]');

    if (!canvas || !input || !pad) return null;

    const ctx = canvas.getContext('2d');
    if (!ctx) return null;

    host.dataset.signatureReady = '1';

    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1f2937';
    ctx.lineWidth = 4;

    let drawing = false;
    let signed = DATA_IMAGE.test(String(input.value || ''));

    const locked = () =>
      input.disabled
      || host.closest('[inert]') !== null
      || host.closest('[aria-disabled="true"]') !== null;

    const setState = hasSignature => {
      signed = hasSignature;
      host.classList.toggle('has-signature', hasSignature);
      if (placeholder) placeholder.hidden = hasSignature;
      if (hasSignature && error) error.hidden = true;
      if (hasSignature) pad.classList.remove('is-invalid');
    };

    const drawExisting = value => {
      if (!DATA_IMAGE.test(String(value || ''))) {
        setState(false);
        return;
      }

      const image = new Image();
      image.onload = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const scale = Math.min(
          canvas.width / image.width,
          canvas.height / image.height
        );
        const width = image.width * scale;
        const height = image.height * scale;
        ctx.drawImage(
          image,
          (canvas.width - width) / 2,
          (canvas.height - height) / 2,
          width,
          height
        );
        setState(true);
      };
      image.src = value;
    };

    const point = event => {
      const rect = canvas.getBoundingClientRect();
      return {
        x: (event.clientX - rect.left) * (canvas.width / rect.width),
        y: (event.clientY - rect.top) * (canvas.height / rect.height)
      };
    };

    const begin = event => {
      if (locked()) return;
      if (event.button !== undefined && event.button !== 0) return;
      event.preventDefault();

      const p = point(event);
      drawing = true;
      canvas.setPointerCapture?.(event.pointerId);
      ctx.beginPath();
      ctx.moveTo(p.x, p.y);
    };

    const move = event => {
      if (!drawing || locked()) return;
      event.preventDefault();

      const p = point(event);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();

      input.value = canvas.toDataURL('image/png');
      setState(true);
      input.dispatchEvent(new Event('input', { bubbles:true }));
      input.dispatchEvent(new Event('change', { bubbles:true }));
    };

    const end = event => {
      if (!drawing) return;
      drawing = false;
      canvas.releasePointerCapture?.(event.pointerId);

      if (signed) {
        input.value = canvas.toDataURL('image/png');
      }
    };

    canvas.addEventListener('pointerdown', begin);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    canvas.addEventListener('pointerleave', event => {
      if (event.buttons === 0) end(event);
    });

    clear?.addEventListener('click', () => {
      if (locked()) return;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      input.value = '';
      setState(false);
      if (error) error.hidden = true;
      input.dispatchEvent(new Event('input', { bubbles:true }));
      input.dispatchEvent(new Event('change', { bubbles:true }));
    });

    input.addEventListener('change', () => {
      const value = String(input.value || '');
      if (DATA_IMAGE.test(value)) {
        drawExisting(value);
      } else {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        setState(false);
      }
    });

    drawExisting(String(input.value || ''));

    return {
      validate() {
        if (input.disabled || host.dataset.signatureRequired !== '1') return true;

        const valid = DATA_IMAGE.test(String(input.value || ''));
        if (!valid) {
          pad.classList.add('is-invalid');
          if (error) error.hidden = false;
          pad.scrollIntoView({ behavior:'smooth', block:'center' });
        }

        return valid;
      }
    };
  }

  function init(root = document) {
    const hosts = [...root.querySelectorAll('[data-digital-signature]')];
    hosts.forEach(host => createController(host));
  }

  function validateForm(form) {
    const hosts = [...form.querySelectorAll('[data-digital-signature]')];
    let valid = true;

    hosts.forEach(host => {
      const input = host.querySelector('[data-signature-value]');
      const pad = host.querySelector('[data-signature-pad]');
      const error = host.querySelector('[data-signature-error]');

      if (!input || input.disabled || host.dataset.signatureRequired !== '1') return;

      const ok = DATA_IMAGE.test(String(input.value || ''));
      if (!ok) {
        valid = false;
        pad?.classList.add('is-invalid');
        if (error) error.hidden = false;
      }
    });

    if (!valid) {
      const first = form.querySelector('[data-digital-signature] [data-signature-pad].is-invalid');
      first?.scrollIntoView({ behavior:'smooth', block:'center' });
      window.setTimeout(() => first?.focus({ preventScroll:true }), 350);
    }

    return valid;
  }

  document.addEventListener('DOMContentLoaded', () => {
    init(document);

    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', event => {
        if (event.submitter?.classList.contains('form-print-button')) return;

        if (!validateForm(form)) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      }, true);
    });
  });

  window.SEDCO_SIGNATURES = { init, validateForm };
})();
