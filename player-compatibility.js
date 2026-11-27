// Compatibility for Seleda exports produced before the Moodle component rename.
(function(root) {
    'use strict';
    for (const name of ['mod_exescorm_launch_next_sco','mod_exescorm_launch_prev_sco',
        'exescorm_current_node','exescorm_tree_node']) {
        if (name in root) continue;
        const modern = name.replace('exescorm','seledascorm');
        Object.defineProperty(root, name, {configurable:true,
            get() { return root[modern]; }, set(value) { root[modern] = value; }});
    }
})(window);
