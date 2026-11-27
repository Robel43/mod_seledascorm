'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
test('legacy learner navigation observes current Seleda functions and tree state',()=>{
    const root={}; const context={window:root}; vm.createContext(context);
    vm.runInContext(fs.readFileSync(path.resolve(__dirname,'../../player-compatibility.js'),'utf8'),context);
    assert.equal(root.mod_exescorm_launch_next_sco,undefined);
    let next=0,previous=0;
    root.mod_seledascorm_launch_next_sco=()=>next++;
    root.mod_seledascorm_launch_prev_sco=()=>previous++;
    root.mod_exescorm_launch_next_sco();root.mod_exescorm_launch_prev_sco();
    assert.equal(next,1);assert.equal(previous,1);
    root.seledascorm_current_node={scoid:3};assert.equal(root.exescorm_current_node.scoid,3);
    root.seledascorm_current_node={scoid:4};assert.equal(root.exescorm_current_node.scoid,4);
    root.seledascorm_tree_node={rootNode:{scoid:1}};assert.equal(root.exescorm_tree_node.rootNode.scoid,1);
});
