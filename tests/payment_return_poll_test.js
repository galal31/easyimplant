const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/payment-return.js'), 'utf8');

function harness() {
    let clock = 0, sequence = 0, calls = 0, failure = false, paid = false;
    const timers = new Map(), handlers = {}, elements = {};
    for (const id of ['payment-return','payment-check','payment-check-status','payment-title','payment-message','payment-icon','payment-amount','payment-amount-box','payment-return-link']) {
        elements[id] = {textContent:'',disabled:false,classList:{toggle() {}},addEventListener(event,fn) { this[event] = fn; }};
    }
    elements['payment-return'].dataset = {token:'a'.repeat(64),returnState:'',terminal:'false',status:'pending'};
    const sandbox = {
        document:{getElementById:id=>elements[id]},
        window:{location:{href:'http://localhost/easyimplant/xpay_return?token=test'},addEventListener:(event,fn)=>handlers[event]=fn},
        Date:{now:()=>clock}, URL, AbortController,
        setTimeout:(fn,ms)=>{ const id=++sequence; timers.set(id,{fn,time:clock+ms}); return id; },
        clearTimeout:id=>timers.delete(id),
        fetch:async(url,options)=> {
            calls++;
            assert.equal(url.pathname,'/easyimplant/api/payment_return_status.php');
            assert.equal(options.credentials,'omit');
            if (failure) throw new Error('Offline');
            return {ok:true,json:async()=>({state:paid?'paid':'pending',terminal:paid,title:paid?'Payment confirmed':'Verifying',message:'Latest status',amount:'1,500 EGP',request_url:'view_request.php?id=1'})};
        },
    };
    vm.runInNewContext(source,sandbox);
    async function flush() { for (let i=0;i<12;i++) await Promise.resolve(); }
    async function tick() {
        const next = [...timers].sort((a,b)=>a[1].time-b[1].time)[0];
        if (!next) return false;
        timers.delete(next[0]); clock=next[1].time; next[1].fn(); await flush(); return true;
    }
    return {elements,timers,tick,flush,setPaid:()=>paid=true,setFailure:value=>failure=value,calls:()=>calls};
}

(async()=> {
    const delayed=harness();
    await delayed.tick();
    assert.equal(delayed.calls(),1);
    delayed.setPaid(); await delayed.tick();
    assert.equal(delayed.elements['payment-title'].textContent,'Payment confirmed');
    assert.equal(delayed.timers.size,0,'Polling continued after confirmation');

    const bounded=harness();
    for (let i=0;i<40 && bounded.timers.size;i++) await bounded.tick();
    assert.equal(bounded.calls(),30,'Polling did not stop at two minutes');
    assert.equal(bounded.timers.size,0);
    assert.match(bounded.elements['payment-check-status'].textContent,/taking longer/);
    bounded.elements['payment-check'].click(); await bounded.flush();
    assert.equal(bounded.calls(),31,'Manual retry did not resume');
    assert.equal(bounded.timers.size,1);

    const offline=harness(); offline.setFailure(true); await offline.tick();
    assert.match(offline.elements['payment-check-status'].textContent,/connection/);
    assert.equal(offline.elements['payment-check'].disabled,false,'Offline failure left retry disabled');
    offline.setFailure(false); offline.setPaid(); offline.elements['payment-check'].click(); await offline.flush();
    assert.equal(offline.elements['payment-return'].dataset.status,'paid');
    assert.equal(offline.timers.size,0);
    console.log('Automatic confirmation, bounded polling, manual retry, and network recovery passed.');
})().catch(error=>{ console.error(error); process.exitCode=1; });
