(() => {
    'use strict';
    window.lucide?.createIcons();
    document.getElementById('print-poster')?.addEventListener('click',()=>window.print());
})();
