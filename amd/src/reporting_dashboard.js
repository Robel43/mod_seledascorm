// Progressive enhancement only: all filters and report links work without JavaScript.
export const init = () => {
    document.querySelectorAll('[data-rpt-submit]').forEach(field => {
        field.addEventListener('change', () => {
            if (field.dataset.rptSubmit === 'course') field.form.elements.id.value = '0';
            const group = field.form.elements.group;
            if (group) group.value = '0';
            field.form.requestSubmit();
        });
    });
    document.querySelectorAll('[data-rpt-pages]').forEach(panel => {
        if (panel.dataset.rptReady) return;
        panel.dataset.rptReady = '1';
        const pages = [...panel.querySelectorAll('.sd-page-bar')];
        const preview = panel.querySelector('.sd-page-preview');
        const more = panel.querySelector('.sd-more-pages');
        const rest = panel.querySelector('.sd-page-rest');
        const search = panel.querySelector('[data-rpt-page-search]');
        const sort = panel.querySelector('[data-rpt-page-sort]');
        const count = panel.querySelector('.sd-page-visible-count');
        let matching = pages;
        const updateCount = () => {
            const shown = more?.open ? matching.length : Math.min(6, matching.length);
            count.textContent = count.dataset.rptCountLabel.replace('{shown}', shown).replace('{total}', matching.length);
        };
        const refresh = () => {
            const term = search.value.trim().toLocaleLowerCase();
            matching = pages.filter(page => page.dataset.name.toLocaleLowerCase().includes(term));
            matching.sort((a, b) => {
                const order = Number(a.dataset.order) - Number(b.dataset.order);
                if (sort.value === 'unfinished') return Number(b.dataset.unfinished) - Number(a.dataset.unfinished) || order;
                if (sort.value === 'completed') return Number(a.dataset.completed) - Number(b.dataset.completed) || order;
                return order;
            });
            preview.replaceChildren(...matching.slice(0, 6));
            if (more) {
                rest.replaceChildren(...matching.slice(6));
                more.hidden = matching.length <= 6;
                const summary = more.querySelector('summary');
                summary.textContent = summary.dataset.rptMoreLabel.replace('{count}', matching.length);
            }
            updateCount();
        };
        panel.querySelector('.sd-page-tools').hidden = false;
        search.addEventListener('input', refresh);
        sort.addEventListener('change', refresh);
        more?.addEventListener('toggle', updateCount);
        refresh();
    });
    document.querySelectorAll('[data-rpt-chart]').forEach(chart => {
        if (chart.dataset.rptReady) return;
        chart.dataset.rptReady = '1';
        const rows = [...chart.querySelectorAll('[data-rpt-chart-item]')];
        const detail = chart.querySelector('.sd-chart-selection');
        let selected = null;
        const show = row => {
            if (!detail) return;
            detail.hidden = !row;
            detail.textContent = row?.dataset.detail || row?.dataset.label || '';
        };
        rows.forEach(row => {
            row.setAttribute('tabindex', '0');
            row.setAttribute('role', 'button');
            row.setAttribute('aria-label', row.dataset.label);
            row.setAttribute('aria-pressed', 'false');
            const activate = () => {
                selected = selected === row ? null : row;
                rows.forEach(item => item.setAttribute('aria-pressed', String(item === selected)));
                show(selected);
            };
            row.addEventListener('click', activate);
            row.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(); }
            });
            row.addEventListener('mouseenter', () => show(row));
            row.addEventListener('mouseleave', () => show(selected));
            row.addEventListener('focus', () => show(row));
            row.addEventListener('blur', () => show(selected));
        });
    });
};
