// All report navigation and figures work without JavaScript.
export const init = () => {
    const sidebar = document.querySelector('.sd-catalogue details');
    if (sidebar && matchMedia('(max-width:700px)').matches) sidebar.open = false;
};
