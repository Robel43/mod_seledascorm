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
};
