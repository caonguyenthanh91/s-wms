/**
 * Nút ✕ xóa nhanh cho mọi ô nhập liệu (text, search, number, email, tel, url, textarea).
 * - Tự áp dụng cho cả ô tạo động (delegated event), không cần sửa từng trang.
 * - Chỉ hiện khi ô đang focus và có nội dung; bỏ qua ô readonly/disabled.
 * - Bỏ qua ô có thuộc tính data-no-clear (ô đã có nút xóa riêng).
 * - Nếu ô đã có icon bên phải (padding-right lớn, VD nút QR) thì ✕ nằm ngay bên trái icon.
 */
(function ($) {
    if (!$) return;

    const SELECTOR = 'input[type="text"], input[type="search"], input[type="number"], input[type="email"], '
                   + 'input[type="tel"], input[type="url"], input:not([type]), textarea';
    const BTN_SIZE = 20;
    const $btn = $('<button type="button" tabindex="-1" aria-label="Xóa nội dung">✕</button>').css({
        position: 'fixed', display: 'none', zIndex: 10000, width: BTN_SIZE + 'px', height: BTN_SIZE + 'px',
        borderRadius: '9999px', border: 'none', background: '#e5e7eb', color: '#4b5563',
        fontSize: '11px', fontWeight: 700, lineHeight: BTN_SIZE + 'px', textAlign: 'center',
        padding: 0, cursor: 'pointer', boxShadow: '0 1px 2px rgba(0,0,0,.08)'
    }).hover(
        function () { $(this).css('background', '#d1d5db'); },
        function () { $(this).css('background', '#e5e7eb'); }
    );
    let current = null;

    function eligible(el) {
        return el && $(el).is(SELECTOR) && !el.readOnly && !el.disabled && !el.hasAttribute('data-no-clear');
    }

    // Chừa chỗ cho nút ✕ để chữ không bị che; ghi nhớ padding gốc để trả lại khi rời ô
    function reservePadding(el) {
        if (el.dataset.clearPadOrig !== undefined) return;
        const pr = parseFloat(getComputedStyle(el).paddingRight) || 0;
        el.dataset.clearPadOrig = el.style.paddingRight || '';
        el.dataset.clearIconRight = pr >= 24 ? pr : 0; // có icon sẵn bên phải
        el.style.paddingRight = (pr >= 24 ? pr + BTN_SIZE + 6 : BTN_SIZE + 10) + 'px';
    }

    function restorePadding(el) {
        if (!el || el.dataset.clearPadOrig === undefined) return;
        el.style.paddingRight = el.dataset.clearPadOrig;
        delete el.dataset.clearPadOrig;
        delete el.dataset.clearIconRight;
    }

    function place() {
        const el = current;
        if (!el || !document.body.contains(el) || !el.value || !eligible(el)) { $btn.hide(); return; }
        reservePadding(el);
        const r = el.getBoundingClientRect();
        if (!r.width || !r.height) { $btn.hide(); return; }
        const iconRight = parseFloat(el.dataset.clearIconRight) || 0;
        const right = iconRight ? iconRight : 6;
        const top = el.tagName === 'TEXTAREA' ? r.top + 6 : r.top + (r.height - BTN_SIZE) / 2;
        $btn.css({ left: (r.right - right - BTN_SIZE) + 'px', top: top + 'px' }).show();
    }

    $(function () { $btn.appendTo(document.body); });

    $(document)
        .on('focusin', SELECTOR, function () {
            if (current && current !== this) restorePadding(current);
            current = eligible(this) ? this : null;
            place();
        })
        .on('input change keyup', SELECTOR, function () { if (this === current) place(); })
        .on('focusout', SELECTOR, function () {
            const el = this;
            // Trì hoãn để click vào ✕ kịp xử lý trước khi ẩn
            setTimeout(function () {
                if (document.activeElement === el) return;
                if (current === el) { current = null; $btn.hide(); }
                restorePadding(el);
            }, 150);
        });

    // Giữ focus ở ô khi bấm ✕
    $btn.on('mousedown touchstart', function (e) { e.preventDefault(); });
    $btn.on('click', function (e) {
        e.preventDefault();
        const el = current;
        if (!el) return;
        el.value = '';
        // Báo cho code của từng trang biết nội dung đã đổi (lọc lại danh sách, ẩn gợi ý...)
        // Sự kiện native -> cả listener jQuery lẫn addEventListener đều nhận đúng 1 lần
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.focus();
        $btn.hide();
    });

    $(window).on('scroll resize', function () { if (current) place(); });
    document.addEventListener('scroll', function () { if (current) place(); }, true);
})(window.jQuery);
