(()=>{var es=Object.defineProperty;var _i=e=>{throw TypeError(e)};var ts=(e,t,n)=>t in e?es(e,t,{enumerable:!0,configurable:!0,writable:!0,value:n}):e[t]=n;var R=(e,t,n)=>ts(e,typeof t!="symbol"?t+"":t,n),na=(e,t,n)=>t.has(e)||_i("Cannot "+n);var h=(e,t,n)=>(na(e,t,"read from private field"),n?n.call(e):t.get(e)),I=(e,t,n)=>t.has(e)?_i("Cannot add the same private member more than once"):t instanceof WeakSet?t.add(e):t.set(e,n),T=(e,t,n,r)=>(na(e,t,"write to private field"),r?r.call(e,n):t.set(e,n),n),N=(e,t,n)=>(na(e,t,"access private method"),n);var Na=Array.isArray,ns=Array.prototype.indexOf,Ar=Array.prototype.includes,rs=Array.from,Tr=Object.keys,Tn=Object.defineProperty,bn=Object.getOwnPropertyDescriptor,as=Object.getOwnPropertyDescriptors,is=Object.prototype,os=Array.prototype,Wi=Object.getPrototypeOf,ki=Object.isExtensible,Ft=()=>{};function ls(e){for(var t=0;t<e.length;t++)e[t]()}function qi(){var e,t,n=new Promise((r,a)=>{e=r,t=a});return{promise:n,resolve:e,reject:t}}var we=2,$n=4,Ur=8,Da=1<<24,lt=16,ut=32,St=64,da=128,Ua=256,Qe=512,ge=1024,me=2048,ct=4096,et=8192,Ye=16384,cn=32768,$r=1<<25,jt=65536,Rr=1<<17,ss=1<<18,un=1<<19,cs=1<<20,on=65536,Pr=1<<21,yn=1<<22,Vt=1<<23,wn=Symbol("$state"),Ji=Symbol("component"),us=Symbol("legacy props"),fs=Symbol(""),kr=Symbol("attributes"),va=Symbol("class"),pa=Symbol("style"),zn=Symbol("text"),qn=Symbol("form reset"),lr=new class extends Error{constructor(){super(...arguments);R(this,"name","StaleReactionError");R(this,"message","The reaction that called `getAbortSignal()` was re-run or destroyed")}},zi,sr=!!((zi=globalThis.document)!=null&&zi.contentType)&&globalThis.document.contentType.includes("xml"),cr=3,ur=8;function Zi(e){return e===this.v}function Xi(e,t){return e!=e?t==t:e!==t||e!==null&&typeof e=="object"||typeof e=="function"}function hs(e){return!Xi(e,this.v)}function ds(e){throw new Error("https://svelte.dev/e/lifecycle_outside_component")}function vs(){throw new Error("https://svelte.dev/e/async_derived_orphan")}function ps(e){throw new Error("https://svelte.dev/e/effect_in_teardown")}function gs(){throw new Error("https://svelte.dev/e/effect_in_unowned_derived")}function ms(e){throw new Error("https://svelte.dev/e/effect_orphan")}function bs(){throw new Error("https://svelte.dev/e/effect_update_depth_exceeded")}function ys(){throw new Error("https://svelte.dev/e/hydration_failed")}function ws(){throw new Error("https://svelte.dev/e/state_descriptors_fixed")}function _s(){throw new Error("https://svelte.dev/e/state_prototype_fixed")}function ks(){throw new Error("https://svelte.dev/e/state_unsafe_mutation")}function xs(){throw new Error("https://svelte.dev/e/svelte_boundary_reset_onerror")}var Es=1,Ss=2,Ha="[",Qi="[!",xi="[?",eo="]",ln={},fe=Symbol("uninitialized"),to="http://www.w3.org/1999/xhtml",Cs="http://www.w3.org/2000/svg",As="http://www.w3.org/1998/Math/MathML",Ts="@attach",Ae=null;function Rn(e){Ae=e}function Mt(e,t=!1,n){Ae={p:Ae,i:!1,c:null,e:null,s:e,x:null,r:M,l:null}}function Nt(e){var t=Ae,n=t.e;if(n!==null){t.e=null;for(var r of n)Oo(r)}return e!==void 0&&(t.x=e),t.i=!0,Ae=t.p,Fa(e)}function Fa(e={}){return Tn(e,Ji,{value:!0}),e}function no(){return!0}var Wt=[];function ro(){var e=Wt;Wt=[],ls(e)}function kt(e){if(Wt.length===0&&!Zn){var t=Wt;queueMicrotask(()=>{t===Wt&&ro()})}Wt.push(e)}function $s(){for(;Wt.length>0;)ro()}function Rs(){console.warn("https://svelte.dev/e/derived_inert")}function fr(e){console.warn("https://svelte.dev/e/hydration_mismatch")}function Ps(){console.warn("https://svelte.dev/e/select_multiple_invalid_value")}function Is(){console.warn("https://svelte.dev/e/svelte_boundary_reset_noop")}var L=!1;function xt(e){L=e}var D;function Oe(e){if(e===null)throw fr(),ln;return D=e}function sn(){return Oe(At(D))}function he(e){if(L){if(At(D)!==null)throw fr(),ln;D=e}}function Va(e=1){if(L){for(var t=e,n=D;t--;)n=At(n);D=n}}function ja(e=!0){for(var t=0,n=D;;){if(n.nodeType===ur){var r=n.data;if(r===eo){if(t===0)return n;t-=1}else(r===Ha||r===Qi||r[0]==="["&&!isNaN(Number(r.slice(1))))&&(t+=1)}var a=At(n);e&&n.remove(),n=a}}function ao(e){if(!e||e.nodeType!==ur)throw fr(),ln;return e.data}function Ot(e){if(typeof e!="object"||e===null||wn in e||Ji in e)return e;let t=Wi(e);if(t!==is&&t!==os)return e;var n=new Map,r=Na(e),a=V(0),i=an,s=l=>{if(an===i)return l();var c=H,f=an;tt(null),Ri(i);var d=l();return tt(c),Ri(f),d};return r&&n.set("length",V(e.length)),new Proxy(e,{defineProperty(l,c,f){(!("value"in f)||f.configurable===!1||f.enumerable===!1||f.writable===!1)&&ws();var d=n.get(c);return d===void 0?s(()=>{var m=V(f.value);return n.set(c,m),m}):S(d,f.value,!0),!0},deleteProperty(l,c){var f=n.get(c);if(f===void 0){if(c in l){let d=s(()=>V(fe));n.set(c,d),Xn(a)}}else S(f,fe),Xn(a);return!0},get(l,c,f){var v;if(c===wn)return e;var d=n.get(c),m=c in l;if(d===void 0&&(!m||(v=bn(l,c))!=null&&v.writable)&&(d=s(()=>{var b=Ot(m?l[c]:fe),y=V(b);return y}),n.set(c,d)),d!==void 0){var g=o(d);return g===fe?void 0:g}return Reflect.get(l,c,f)},getOwnPropertyDescriptor(l,c){var f=Reflect.getOwnPropertyDescriptor(l,c);if(f&&"value"in f){var d=n.get(c);d&&(f.value=o(d))}else if(f===void 0){var m=n.get(c),g=m==null?void 0:m.v;if(m!==void 0&&g!==fe)return{enumerable:!0,configurable:!0,value:g,writable:!0}}return f},has(l,c){var g;if(c===wn)return!0;var f=n.get(c),d=f!==void 0&&f.v!==fe||Reflect.has(l,c);if(f!==void 0||M!==null&&(!d||(g=bn(l,c))!=null&&g.writable)){f===void 0&&(f=s(()=>{var v=d?Ot(l[c]):fe,b=V(v);return b}),n.set(c,f));var m=o(f);if(m===fe)return!1}return d},set(l,c,f,d){var se;var m=n.get(c),g=c in l;if(r&&c==="length")for(var v=f;v<m.v;v+=1){var b=n.get(v+"");b!==void 0?S(b,fe):v in l&&(b=s(()=>V(fe)),n.set(v+"",b))}if(m===void 0)(!g||(se=bn(l,c))!=null&&se.writable)&&(m=s(()=>V(void 0)),S(m,Ot(f)),n.set(c,m));else{g=m.v!==fe;var y=s(()=>Ot(f));S(m,y)}var O=Reflect.getOwnPropertyDescriptor(l,c);if(O!=null&&O.set&&O.set.call(d,f),!g){if(r&&typeof c=="string"){var P=n.get("length"),z=Number(c);Number.isInteger(z)&&z>=P.v&&S(P,z+1)}Xn(a)}return!0},ownKeys(l){o(a);var c=Reflect.ownKeys(l).filter(m=>{var g=n.get(m);return g===void 0||g.v!==fe});for(var[f,d]of n)d.v!==fe&&!(f in l)&&c.push(f);return c},setPrototypeOf(){_s()}})}function Ei(e){try{if(e!==null&&typeof e=="object"&&wn in e)return e[wn]}catch(t){}return e}function io(e,t){return Object.is(Ei(e),Ei(t))}var rn,ga,oo,lo,so;function ma(){if(rn===void 0){rn=window,ga=document,oo=/Firefox/.test(navigator.userAgent);var e=Element.prototype,t=Node.prototype,n=Text.prototype;lo=bn(t,"firstChild").get,so=bn(t,"nextSibling").get,ki(e)&&(e[va]=void 0,e[kr]=null,e[pa]=void 0,e.__e=void 0),ki(n)&&(n[zn]=void 0)}}function ft(e=""){return document.createTextNode(e)}function Ue(e){return lo.call(e)}function At(e){return so.call(e)}function ye(e,t){if(!L)return Ue(e);var n=Ue(D);if(n===null)n=D.appendChild(ft());else if(t&&n.nodeType!==cr){var r=ft();return n==null||n.before(r),Oe(r),r}return t&&Hr(n),Oe(n),n}function vn(e,t=!1){if(!L){var n=Ue(e);return n instanceof Comment&&n.data===""?At(n):n}if(t){if((D==null?void 0:D.nodeType)!==cr){var r=ft();return D==null||D.before(r),Oe(r),r}Hr(D)}return D}function gn(e,t=!1){if(!L)return Ue(e);var n=ye(e,t);return he(e),n}function oe(e,t=1,n=!1){let r=L?D:e;for(var a;t--;)a=r,r=At(r);if(!L)return r;if(n){if((r==null?void 0:r.nodeType)!==cr){var i=ft();return r===null?a==null||a.after(i):r.before(i),Oe(i),i}Hr(r)}return Oe(r),r}function Os(e){e.textContent=""}function Ls(){return!1}function za(e,t,n){return t==null||t===to?n?document.createElement(e,{is:n}):document.createElement(e):n?document.createElementNS(t,e,{is:n}):document.createElementNS(t,e)}function Hr(e){if(e.nodeValue.length<65536)return;let t=e.nextSibling;for(;t!==null&&t.nodeType===cr;)t.remove(),e.nodeValue+=t.nodeValue,t=e.nextSibling}function Ms(e){var t=M;if(t===null)return H.f|=Vt,e;if((t.f&cn)===0&&(t.f&$n)===0)throw e;_t(e,t)}function _t(e,t){if(!(t!==null&&(t.f&Ye)!==0)){for(;t!==null;){if((t.f&da)!==0&&(t.f&(Ye|$r))===0){if((t.f&cn)===0)throw e;try{t.b.error(e);return}catch(n){e=n}}t=t.parent}throw e}}var Ns=-7169;function ue(e,t){e.f=e.f&Ns|t}function Ba(e){(e.f&Qe)!==0||e.deps===null?ue(e,ge):ue(e,ct)}function co(e){if(e!==null)for(let t of e)(t.f&we)===0||(t.f&on)===0||(t.f^=on,co(t.deps))}function uo(e,t,n){(e.f&me)!==0?t.add(e):(e.f&ct)!==0&&n.add(e),co(e.deps),ue(e,ge)}function fo(e,t,n){if(e==null)return t(void 0),Ft;let r=vr(()=>e.subscribe(t,n));return r.unsubscribe?()=>r.unsubscribe():r}var hn=[];function Ds(e,t=Ft){let n=null,r=new Set;function a(l){if(Xi(e,l)&&(e=l,n)){let c=!hn.length;for(let f of r)f[1](),hn.push(f,e);if(c){for(let f=0;f<hn.length;f+=2)hn[f][0](hn[f+1]);hn.length=0}}}function i(l){a(l(e))}function s(l,c=Ft){let f=[l,c];return r.add(f),r.size===1&&(n=t(a,i)||Ft),l(e),()=>{r.delete(f),r.size===0&&n&&(n(),n=null)}}return{set:a,update:i,subscribe:s}}function Bn(e){let t;return fo(e,n=>t=n)(),t}var ba=Symbol("unmounted");function Si(e,t,n){var i;let r=(i=n[t])!=null?i:n[t]={store:null,source:Eo(void 0),unsubscribe:Ft};if(r.store!==e&&!(ba in n))if(r.unsubscribe(),r.store=e!=null?e:null,e==null)r.source.v=void 0,r.unsubscribe=Ft;else{var a=!0;r.unsubscribe=fo(e,s=>{a?r.source.v=s:S(r.source,s)}),a=!1}return e&&ba in n?Bn(e):o(r.source)}function Us(){let e={};function t(){Fr(()=>{for(var n in e)e[n].unsubscribe();Tn(e,ba,{enumerable:!1,value:!0})})}return[e,t]}function Hs(e,t){if(t){let n=document.body;e.autofocus=!0,kt(()=>{document.activeElement===n&&e.focus()})}}var Ci=!1;function ho(){Ci||(Ci=!0,document.addEventListener("reset",e=>{Promise.resolve().then(()=>{var t;if(!e.defaultPrevented)for(let n of e.target.elements)(t=n[qn])==null||t.call(n)})},{capture:!0}))}function On(e){var t=H,n=M;tt(null),Ct(null);try{return e()}finally{tt(t),Ct(n)}}function Fs(e,t,n,r=n){e.addEventListener(t,()=>On(n));let a=e[qn];a?e[qn]=()=>{a(),r(!0)}:e[qn]=()=>r(!0),ho()}function vo(e,t,n,r){let a=Ka;var i=e.filter(v=>!v.settled),s=t.map(a);if(n.length===0&&i.length===0){r(s);return}var l=M,c=Vs(),f=i.length===1?i[0].promise:i.length>1?Promise.all(i.map(v=>v.promise)):null;function d(v){if((l.f&Ye)===0){c();try{r([...s,...v])}catch(b){_t(b,l)}Ir()}}var m=po();if(n.length===0){f.then(()=>d([])).finally(m);return}function g(){Promise.all(n.map(v=>js(v))).then(d).catch(v=>_t(v,l)).finally(m)}f?f.then(()=>{c(),g(),Ir()}):g()}function Vs(){var e=M,t=H,n=Ae,r=C;return function(i=!0){Ct(e),tt(t),Rn(n),i&&(e.f&Ye)===0&&(r==null||r.activate(),r==null||r.apply())}}function Ir(e=!0){Ct(null),tt(null),Rn(null),e&&(C==null||C.deactivate())}function po(){var e=M,t=e.b,n=C,r=!!(t!=null&&t.is_rendered());return t==null||t.update_pending_count(1,n),n.increment(r,e),()=>{t==null||t.update_pending_count(-1,n),n.decrement(r,e)}}function Ka(e){var t=we|me;return M!==null&&(M.f|=un),{ctx:Ae,deps:null,effects:null,equals:Zi,f:t,fn:e,reactions:null,rv:0,v:fe,wv:0,parent:M,ac:null}}var Kn=Symbol("obsolete");function js(e,t,n){let r=M;r===null&&vs();var a=void 0,i=hr(fe),s=!H,l=new Set;return tc(()=>{var v,b;var c=M,f=qi();a=f.promise;try{Promise.resolve(e()).then(f.resolve,y=>{y!==lr&&f.reject(y)}).finally(Ir)}catch(y){f.reject(y),Ir()}var d=C;if(s){if((c.f&cn)!==0)var m=po();if((v=r.b)!=null&&v.is_rendered())(b=d.async_deriveds.get(c))==null||b.reject(Kn);else for(let y of l.values())y.reject(Kn);l.add(f),d.async_deriveds.set(c,f)}let g=(y,O=void 0)=>{m==null||m(),l.delete(f),O!==Kn&&(d.activate(),O?(i.f|=Vt,Lr(i,O)):((i.f&Vt)!==0&&(i.f^=Vt),Lr(i,y)),d.deactivate())};f.promise.then(g,y=>g(null,y||"unknown"))}),Fr(()=>{for(let c of l)c.reject(Kn)}),new Promise(c=>{function f(d){function m(){d===a?c(i):f(a)}d.then(m,m)}f(a)})}function Re(e){let t=Ka(e);return Co(t),t}function zs(e){var t=e.effects;if(t!==null){e.effects=null;for(var n=0;n<t.length;n+=1)Ee(t[n])}}function Ya(e){var t,n=M,r=e.parent;if(!Lt&&r!==null&&e.v!==fe&&(r.f&(Ye|et))!==0)return Rs(),e.v;Ct(r);try{e.f&=~on,zs(e),t=Ro(e)}finally{Ct(n)}return t}function go(e){var t=Ya(e);if(!e.equals(t)&&(e.wv=To(),(!(C!=null&&C.is_fork)||e.deps===null)&&(C!==null?(C.capture(e,t,!0),Jn==null||Jn.capture(e,t,!0)):e.v=t,e.deps===null))){ue(e,ge);return}Lt||(xe!==null?(qa()||C!=null&&C.is_fork)&&xe.set(e,t):Ba(e))}function Bs(e){var t;if(e.effects!==null)for(let n of e.effects)(n.teardown||n.ac)&&((t=n.teardown)==null||t.call(n),n.ac!==null&&On(()=>{n.ac.abort(lr),n.ac=null}),n.fn!==null&&(n.teardown=Ft),er(n,0),Za(n))}function mo(e){if(e.effects!==null)for(let t of e.effects)t.teardown&&t.fn!==null&&Pn(t)}var ra=null,dn=null,C=null,Jn=null,xe=null,ya=null,Zn=!1,aa=!1,mn=null,xr=null,Ai=0;var Ks=1,_n,Ut,Xt,kn,xn,En,$t,Sn,De,tr,Rt,at,mt,Cn,Qt,Z,wa,Yn,_a,bo,yo,pn,Ys,Gn,Mr=class Mr{constructor(){I(this,Z);R(this,"id",Ks++);I(this,_n,!1);R(this,"linked",!0);I(this,Ut,null);I(this,Xt,null);R(this,"async_deriveds",new Map);R(this,"current",new Map);R(this,"previous",new Map);I(this,kn,new Set);I(this,xn,new Set);I(this,En,0);I(this,$t,new Map);I(this,Sn,null);I(this,De,[]);I(this,tr,[]);I(this,Rt,new Set);I(this,at,new Set);I(this,mt,new Map);I(this,Cn,new Set);R(this,"is_fork",!1);I(this,Qt,!1);dn===null?ra=dn=this:(T(dn,Xt,this),T(this,Ut,dn)),dn=this}skip_effect(t){h(this,mt).has(t)||h(this,mt).set(t,{d:[],m:[]}),h(this,Cn).delete(t)}unskip_effect(t,n=r=>this.schedule(r)){var r=h(this,mt).get(t);if(r){h(this,mt).delete(t);for(var a of r.d)ue(a,me),n(a);for(a of r.m)ue(a,ct),n(a)}h(this,Cn).add(t)}capture(t,n,r=!1){t.v!==fe&&!this.previous.has(t)&&this.previous.set(t,t.v),(t.f&Vt)===0&&(this.current.set(t,[n,r]),xe==null||xe.set(t,n)),this.is_fork||(t.v=n)}activate(){C=this}deactivate(){C=null,xe=null}flush(){try{aa=!0,C=this,N(this,Z,Yn).call(this)}finally{Ai=0,ya=null,mn=null,xr=null,aa=!1,C=null,xe=null,Et.clear()}}discard(){var t;for(let n of h(this,xn))n(this);h(this,xn).clear();for(let n of this.async_deriveds.values())n.reject(Kn);N(this,Z,Gn).call(this),(t=h(this,Sn))==null||t.resolve()}register_created_effect(t){h(this,tr).push(t)}increment(t,n){var r;if(T(this,En,h(this,En)+1),t){let a=(r=h(this,$t).get(n))!=null?r:0;h(this,$t).set(n,a+1)}}decrement(t,n){var r;if(T(this,En,h(this,En)-1),t){let a=(r=h(this,$t).get(n))!=null?r:0;a===1?h(this,$t).delete(n):h(this,$t).set(n,a-1)}h(this,Qt)||(T(this,Qt,!0),kt(()=>{T(this,Qt,!1),this.linked&&this.flush()}))}transfer_effects(t,n){for(let r of t)h(this,Rt).add(r);for(let r of n)h(this,at).add(r);t.clear(),n.clear()}oncommit(t){h(this,kn).add(t)}ondiscard(t){h(this,xn).add(t)}settled(){var t;return((t=h(this,Sn))!=null?t:T(this,Sn,qi())).promise}static ensure(){if(C===null){let t=C=new Mr;!aa&&!Zn&&kt(()=>{h(t,_n)||t.flush()})}return C}apply(){{xe=null;return}}schedule(t){var a;if(ya=t,(a=t.b)!=null&&a.is_pending&&(t.f&($n|Ur|Da))!==0&&(t.f&cn)===0){t.b.defer_effect(t);return}for(var n=t;n.parent!==null;){n=n.parent;var r=n.f;if(mn!==null&&n===M&&(H===null||(H.f&we)===0))return;if((r&(St|ut))!==0){if((r&ge)===0)return;n.f^=ge}}h(this,De).push(n)}};_n=new WeakMap,Ut=new WeakMap,Xt=new WeakMap,kn=new WeakMap,xn=new WeakMap,En=new WeakMap,$t=new WeakMap,Sn=new WeakMap,De=new WeakMap,tr=new WeakMap,Rt=new WeakMap,at=new WeakMap,mt=new WeakMap,Cn=new WeakMap,Qt=new WeakMap,Z=new WeakSet,wa=function(){if(this.is_fork)return!0;for(let r of h(this,$t).keys()){for(var t=r,n=!1;t.parent!==null;){if(h(this,mt).has(t)){n=!0;break}t=t.parent}if(!n)return!0}return!1},Yn=function(){var c,f,d,m;T(this,_n,!0),Ai++>1e3&&(N(this,Z,Gn).call(this),Gs());for(let g of h(this,Rt))h(this,at).delete(g),ue(g,me),this.schedule(g);for(let g of h(this,at))ue(g,ct),this.schedule(g);let t=h(this,De);T(this,De,[]),this.apply();var n=mn=[],r=[],a=xr=[];for(let g of t)try{N(this,Z,_a).call(this,g,n,r)}catch(v){throw ko(g),N(this,Z,wa).call(this)||this.discard(),v}if(C=null,a.length>0){var i=Mr.ensure();for(let g of a)i.schedule(g)}if(mn=null,xr=null,N(this,Z,wa).call(this)){N(this,Z,pn).call(this,r),N(this,Z,pn).call(this,n);for(let[g,v]of h(this,mt))_o(g,v);a.length>0&&N(c=C,Z,Yn).call(c);return}let s=N(this,Z,bo).call(this);if(s){N(this,Z,pn).call(this,r),N(this,Z,pn).call(this,n),N(f=s,Z,yo).call(f,this);return}h(this,Rt).clear(),h(this,at).clear();for(let g of h(this,kn))g(this);h(this,kn).clear(),Jn=this,Ti(r),Ti(n),Jn=null,(d=h(this,Sn))==null||d.resolve();var l=C;if(h(this,En)===0&&(h(this,De).length===0||l!==null)&&N(this,Z,Gn).call(this),h(this,De).length>0)if(l!==null){let g=l;h(g,De).push(...h(this,De).filter(v=>!h(g,De).includes(v)))}else l=this;l!==null&&(Et.clear(),N(m=l,Z,Yn).call(m))},_a=function(t,n,r){t.f^=ge;for(var a=t.first;a!==null;){var i=a.f,s=(i&(ut|St))!==0,l=s&&(i&ge)!==0,c=l||(i&et)!==0||h(this,mt).has(a);if(!c&&a.fn!==null){s?a.f^=ge:(i&$n)!==0?n.push(a):dr(a)&&((i&lt)!==0&&h(this,at).add(a),Pn(a));var f=a.first;if(f!==null){a=f;continue}}for(;a!==null;){var d=a.next;if(d!==null){a=d;break}a=a.parent}}},bo=function(){for(var t=h(this,Ut);t!==null;){if(!t.is_fork){for(let[n,[,r]]of this.current)if(t.current.has(n)&&!r)return t}t=h(t,Ut)}return null},yo=function(t){var r;for(let[a,i]of t.current)!this.previous.has(a)&&t.previous.has(a)&&this.previous.set(a,t.previous.get(a)),this.current.set(a,i);for(let[a,i]of t.async_deriveds){let s=this.async_deriveds.get(a);s&&i.promise.then(s.resolve).catch(s.reject)}t.async_deriveds.clear(),this.transfer_effects(h(t,Rt),h(t,at));let n=a=>{var i=a.reactions;if(i!==null&&!((a.f&we)!==0&&(a.f&(me|ct))===0))for(let c of i){var s=c.f;if((s&we)!==0)n(c);else{var l=c;s&(yn|lt)&&!this.async_deriveds.has(l)&&(h(this,at).delete(l),ue(l,me),this.schedule(l))}}};for(let a of this.current.keys())n(a);this.oncommit(()=>t.discard()),N(r=t,Z,Gn).call(r),C=this,N(this,Z,Yn).call(this)},pn=function(t){for(var n=0;n<t.length;n+=1)uo(t[n],h(this,Rt),h(this,at))},Ys=function(){var m;for(let g=ra;g!==null;g=h(g,Xt)){var t=g.id<this.id,n=[];for(let[v,[b,y]]of this.current){if(g.current.has(v)){var r=g.current.get(v)[0];if(t&&b!==r)g.current.set(v,[b,y]);else continue}n.push(v)}if(t)for(let[v,b]of this.async_deriveds){let y=g.async_deriveds.get(v);y&&b.promise.then(y.resolve).catch(y.reject)}var a=[...g.current.keys()].filter(v=>!g.current.get(v)[1]);if(!(!h(g,_n)||a.length===0)){var i=a.filter(v=>!this.current.has(v));if(i.length===0)t&&g.discard();else if(n.length>0){if(t)for(let v of h(this,Cn))g.unskip_effect(v,b=>{var y;(b.f&(lt|yn))!==0?g.schedule(b):N(y=g,Z,pn).call(y,[b])});g.activate();var s=new Set,l=new Map;for(var c of n)wo(c,i,s,l);l=new Map;var f=[...g.current].filter(([v,b])=>{let y=this.current.get(v);return y?y[0]!==b[0]||y[1]!==b[1]:!0}).map(([v])=>v);if(f.length>0)for(let v of h(this,tr))(v.f&(Ye|et|Rr))===0&&Ga(v,f,l)&&((v.f&(yn|lt))!==0?(ue(v,me),g.schedule(v)):h(g,Rt).add(v));if(h(g,De).length>0&&!h(g,Qt)){g.apply();for(var d of h(g,De))N(m=g,Z,_a).call(m,d,[],[]);T(g,De,[])}g.deactivate()}}}},Gn=function(){if(this.linked){var t=h(this,Ut),n=h(this,Xt);t===null?ra=n:T(t,Xt,n),n===null?dn=t:T(n,Ut,t),this.linked=!1}};var zt=Mr;function te(e){var t=Zn;Zn=!0;try{for(var n;;){if($s(),C===null)return n;C.flush()}}finally{Zn=t}}function Gs(){try{bs()}catch(e){_t(e,ya)}}var rt=null;function Ti(e){var t=e.length;if(t!==0){for(var n=0;n<t;){var r=e[n++];if((r.f&(Ye|et))===0&&dr(r)&&(rt=new Set,Pn(r),r.deps===null&&r.first===null&&r.nodes===null&&r.teardown===null&&r.ac===null&&Do(r),(rt==null?void 0:rt.size)>0)){Et.clear();for(let a of rt){if((a.f&(Ye|et))!==0)continue;let i=[a],s=a.parent;for(;s!==null;)rt.has(s)&&(rt.delete(s),i.push(s)),s=s.parent;for(let l=i.length-1;l>=0;l--){let c=i[l];(c.f&(Ye|et))===0&&Pn(c)}}rt.clear()}}rt=null}}function wo(e,t,n,r){if(!n.has(e)&&(n.add(e),e.reactions!==null))for(let a of e.reactions){let i=a.f;(i&we)!==0?wo(a,t,n,r):(i&(yn|lt))!==0&&(i&me)===0&&Ga(a,t,r)&&(ue(a,me),Wa(a))}}function Ga(e,t,n){let r=n.get(e);if(r!==void 0)return r;if(e.deps!==null)for(let a of e.deps){if(Ar.call(t,a))return!0;if((a.f&we)!==0&&Ga(a,t,n))return n.set(a,!0),!0}return n.set(e,!1),!1}function Wa(e){C.schedule(e)}function _o(e,t){if(!((e.f&ut)!==0&&(e.f&ge)!==0)){(e.f&me)!==0?t.d.push(e):(e.f&ct)!==0&&t.m.push(e),ue(e,ge);for(var n=e.first;n!==null;)_o(n,t),n=n.next}}function ko(e){ue(e,ge);for(var t=e.first;t!==null;)ko(t),t=t.next}var Or=new Set,Et=new Map,xo=!1;function hr(e,t){var n={f:0,v:e,reactions:null,equals:Zi,rv:0,wv:0};return n}function V(e,t){let n=hr(e);return Co(n),n}function Eo(e,t=!1,n=!0){let r=hr(e);return t||(r.equals=hs),r}function S(e,t,n=!1){H!==null&&(!st||(H.f&Rr)!==0)&&no()&&(H.f&(we|lt|yn|Rr))!==0&&(Ke===null||!Ke.has(e))&&ks();let r=n?Ot(t):t;return Lr(e,r,xr)}function Lr(e,t,n=null){if(!e.equals(t)){Lt?Et.set(e,t):Et.has(e)||Et.set(e,e.v);var r=zt.ensure();if(r.capture(e,t),(e.f&we)!==0){let a=e;(e.f&me)!==0&&Ya(a),xe===null&&Ba(a)}e.wv=To(),So(e,me,n),M!==null&&(M.f&ge)!==0&&(M.f&(ut|St))===0&&(Je===null?qs([e]):Je.push(e)),!r.is_fork&&Or.size>0&&!xo&&Ws()}return t}function Ws(){xo=!1;for(let e of Or){(e.f&ge)!==0&&ue(e,ct);let t;try{t=dr(e)}catch(n){t=!0}t&&Pn(e)}Or.clear()}function Xn(e){S(e,e.v+1)}function So(e,t,n){var r=e.reactions;if(r!==null)for(var a=r.length,i=0;i<a;i++){var s=r[i],l=s.f,c=(l&me)===0;if(c&&ue(s,t),(l&Rr)!==0)Or.add(s);else if((l&we)!==0){var f=s;xe==null||xe.delete(f),(l&on)===0&&(l&Qe&&(M===null||(M.f&Pr)===0)&&(s.f|=on),So(f,ct,n))}else if(c){var d=s;(l&lt)!==0&&rt!==null&&rt.add(d),n!==null?n.push(d):Wa(d)}}}var Er=!1,Lt=!1;function $i(e){Lt=e}var H=null,st=!1;function tt(e){H=e}var M=null;function Ct(e){M=e}var Ke=null;function Co(e){H!==null&&(Ke!=null?Ke:Ke=new Set).add(e)}var He=null,Be=0,Je=null;function qs(e){Je=e}var Ao=1,qt=0,an=qt;function Ri(e){an=e}function To(){return++Ao}function dr(e){var t=e.f;if((t&me)!==0)return!0;if(t&we&&(e.f&=~on),(t&ct)!==0){for(var n=e.deps,r=n.length,a=0;a<r;a++){var i=n[a];if(dr(i)&&go(i),i.wv>e.wv)return!0}(t&Qe)!==0&&xe===null&&ue(e,ge)}return!1}function $o(e,t,n=!0){var r=e.reactions;if(r!==null&&!(Ke!==null&&Ke.has(e)))for(var a=0;a<r.length;a++){var i=r[a];(i.f&we)!==0?$o(i,t,!1):t===i&&(n?ue(i,me):(i.f&ge)!==0&&ue(i,ct),Wa(i))}}function Ro(e){var t=He,n=Be,r=Je,a=H,i=Ke,s=Ae,l=st,c=an,f=e.f;He=null,Be=0,Je=null,H=(f&(ut|St))===0?e:null,Ke=null,Rn(e.ctx),st=!1,an=++qt,e.ac!==null&&(On(()=>{e.ac.abort(lr)}),e.ac=null);try{e.f|=Pr;var d=e.fn,m=d();e.f|=cn;var g=Pi(e);if(no()&&Je!==null&&!st&&g!==null&&(e.f&(we|ct|me))===0)for(var v=0;v<Je.length;v++)$o(Je[v],e);if(a!==null&&a!==e){if(qt++,a.deps!==null)for(let b=0;b<n;b+=1)a.deps[b].rv=qt;if(t!==null)for(let b of t)b.rv=qt;Je!==null&&(r===null?r=Je:r.push(...Je))}return(e.f&Vt)!==0&&(e.f^=Vt),m}catch(b){return Pi(e),Ms(b)}finally{e.f^=Pr,He=t,Be=n,Je=r,H=a,Ke=i,Rn(s),st=l,an=c}}function Pi(e){var a,i;var t=e.deps,n=C==null?void 0:C.is_fork;if(He!==null){var r;if(n||er(e,Be),t!==null&&Be>0)for(t.length=Be+He.length,r=0;r<He.length;r++)t[Be+r]=He[r];else e.deps=t=He;if(qa()&&(e.f&Qe)!==0)for(r=Be;r<t.length;r++)((i=(a=t[r]).reactions)!=null?i:a.reactions=[]).push(e)}else!n&&t!==null&&Be<t.length&&(er(e,Be),t.length=Be);return t}function Js(e,t){let n=t.reactions;if(n!==null){var r=ns.call(n,e);if(r!==-1){var a=n.length-1;a===0?n=t.reactions=null:(n[r]=n[a],n.pop())}}if(n===null&&(t.f&we)!==0&&(He===null||!Ar.call(He,t))){var i=t;(i.f&Qe)!==0&&(i.f^=Qe,i.f&=~on),i.v!==fe&&Ba(i),i.ac!==null&&On(()=>{i.ac.abort(lr),i.ac=null,ue(i,me)}),Bs(i),er(i,0)}}function er(e,t){var n=e.deps;if(n!==null)for(var r=t;r<n.length;r++)Js(e,n[r])}function Pn(e){var t=e.f;if((t&Ye)===0){ue(e,ge);var n=M,r=Er;M=e,Er=(t&(ut|St))===0;try{(t&(lt|Da))!==0?nc(e):Za(e),Mo(e);var a=Ro(e);e.teardown=typeof a=="function"?a:null,e.wv=Ao;var i}finally{Er=r,M=n}}}async function Jt(){await Promise.resolve(),te()}function o(e){var d;var t=e.f,n=(t&we)!==0;if(H!==null&&!st){var r=M!==null&&(M.f&Ye)!==0;if(!r&&(Ke===null||!Ke.has(e))){var a=H.deps;if((H.f&Pr)!==0)e.rv<qt&&(e.rv=qt,He===null&&a!==null&&a[Be]===e?Be++:He===null?He=[e]:He.push(e));else{(d=H.deps)!=null||(H.deps=[]),Ar.call(H.deps,e)||H.deps.push(e);var i=e.reactions;i===null?e.reactions=[H]:Ar.call(i,H)||i.push(H)}}}if(Lt&&Et.has(e))return Et.get(e);if(n){var s=e;if(Lt){var l=s.v;return((s.f&ge)===0&&s.reactions!==null||Io(s))&&(l=Ya(s)),Et.set(s,l),l}var c=(s.f&Qe)===0&&!st&&H!==null&&(Er||(H.f&Qe)!==0),f=(s.f&cn)===0;dr(s)&&(c&&(s.f|=Qe),go(s)),c&&!f&&(mo(s),Po(s))}if(xe!=null&&xe.has(e))return xe.get(e);if((e.f&Vt)!==0)throw e.v;return e.v}function Po(e){var t;if(e.f|=Qe,e.deps!==null)for(let n of e.deps)((t=n.reactions)!=null?t:n.reactions=[]).push(e),(n.f&we)!==0&&(n.f&Qe)===0&&(mo(n),Po(n))}function Io(e){if(e.v===fe)return!0;if(e.deps===null)return!1;for(let t of e.deps)if(Et.has(t)||(t.f&we)!==0&&Io(t))return!0;return!1}function vr(e){var t=st;try{return st=!0,e()}finally{st=t}}function Zs(e){M===null&&(H===null&&ms(),gs()),Lt&&ps()}function Xs(e,t){var n=t.last;n===null?t.last=t.first=e:(n.next=e,e.prev=n,t.last=e)}function ht(e,t){var s;var n=M;n!==null&&(n.f&et)!==0&&(e|=et);var r={ctx:Ae,deps:null,nodes:null,f:e|me|Qe,first:null,fn:t,last:null,next:null,parent:n,b:n&&n.b,prev:null,teardown:null,wv:0,ac:null};C==null||C.register_created_effect(r);var a=r;if((e&$n)!==0)mn!==null?mn.push(r):zt.ensure().schedule(r);else if(t!==null){try{Pn(r)}catch(l){throw Ee(r),l}a.deps===null&&a.teardown===null&&a.nodes===null&&a.first===a.last&&(a.f&un)===0&&(a=a.first,(e&lt)!==0&&(e&jt)!==0&&a!==null&&(a.f|=jt))}if(a!==null&&(a.parent=n,n!==null&&Xs(a,n),H!==null&&(H.f&we)!==0&&(e&St)===0)){var i=H;((s=i.effects)!=null?s:i.effects=[]).push(a)}return r}function qa(){return H!==null&&!st}function Fr(e){let t=ht(Ur,null);return ue(t,ge),t.teardown=e,t}function Ne(e){var a;Zs();var t=M.f,n=!H&&(t&ut)!==0&&Ae!==null&&!Ae.i;if(n){var r=Ae;((a=r.e)!=null?a:r.e=[]).push(e)}else return Oo(e)}function Oo(e){return ht($n|cs,e)}function Qs(e){zt.ensure();let t=ht(St|un,e);return()=>{Ee(t)}}function ec(e){zt.ensure();let t=ht(St|un,e);return(n={})=>new Promise(r=>{n.outro?Qn(t,()=>{Ee(t),r(void 0)}):(Ee(t),r(void 0))})}function Ja(e){return ht($n,e)}function tc(e){return ht(yn|un,e)}function Vr(e,t=0){return ht(Ur|t,e)}function Ce(e,t=[],n=[],r=[]){vo(r,t,n,a=>{ht(Ur,()=>{e(...a.map(o))})})}function pr(e,t=0){var n=ht(lt|t,e);return n}function Lo(e,t=0){var n=ht(Da|t,e);return n}function ot(e){return ht(ut|un,e)}function Mo(e){var t=e.teardown;if(t!==null){let n=Lt,r=H;$i(!0),tt(null);try{t.call(null)}catch(a){_t(a,e.parent)}finally{$i(n),tt(r)}}}function Za(e,t=!1){var n=e.first;for(e.first=e.last=null;n!==null;){let a=n.ac;a!==null&&On(()=>{a.abort(lr)});var r=n.next;(n.f&St)!==0?n.parent=null:Ee(n,t),n=r}}function nc(e){for(var t=e.first;t!==null;){var n=t.next;(t.f&ut)===0&&Ee(t),t=n}}function Ee(e,t=!0){var n=!1;(t||(e.f&ss)!==0)&&e.nodes!==null&&e.nodes.end!==null&&(No(e.nodes.start,e.nodes.end),n=!0),e.f|=$r,Za(e,t&&!n),er(e,0);var r=e.nodes&&e.nodes.t;if(r!==null)for(let i of r)i.stop();Mo(e),e.f^=$r,e.f|=Ye;var a=e.parent;a!==null&&a.first!==null&&Do(e),e.next=e.prev=e.teardown=e.ctx=e.deps=e.fn=e.nodes=e.ac=e.b=null}function No(e,t){for(;e!==null;){var n=e===t?null:At(e);e.remove(),e=n}}function Do(e){var t=e.parent,n=e.prev,r=e.next;n!==null&&(n.next=r),r!==null&&(r.prev=n),t!==null&&(t.first===e&&(t.first=r),t.last===e&&(t.last=n))}function Qn(e,t,n=!0){var r=[];e.f|=Ua,Uo(e,r,!0);var a=()=>{n&&Ee(e),t&&t()},i=r.length;if(i>0){var s=()=>--i||a();for(var l of r)l.out(s)}else a()}function Uo(e,t,n){if((e.f&et)===0){e.f^=et;var r=e.nodes&&e.nodes.t;if(r!==null)for(let l of r)(l.is_global||n)&&t.push(l);for(var a=e.first;a!==null;){var i=a.next;if((a.f&St)===0){var s=(a.f&jt)!==0||(a.f&ut)!==0&&(e.f&lt)!==0;Uo(a,t,s?n:!1)}a=i}}}function Ii(e){e.f&=~Ua,Ho(e,!0)}function Ho(e,t){if((e.f&Ua)===0&&(e.f&et)!==0){e.f^=et,(e.f&ge)===0&&(ue(e,me),zt.ensure().schedule(e));for(var n=e.first;n!==null;){var r=n.next,a=(n.f&jt)!==0||(n.f&ut)!==0;Ho(n,a?t:!1),n=r}var i=e.nodes&&e.nodes.t;if(i!==null)for(let s of i)(s.is_global||t)&&s.in()}}function Fo(e,t){if(e.nodes)for(var n=e.nodes.start,r=e.nodes.end;n!==null;){var a=n===r?null:At(n);t.append(n),n=a}}function rc(e){let t=0,n=hr(0),r;return()=>{qa()&&(o(n),Vr(()=>(t===0&&(r=vr(()=>e(()=>Xn(n)))),t+=1,()=>{kt(()=>{t-=1,t===0&&(r==null||r(),r=void 0,Xn(n))})})))}}function Oi(e){let t={get:n=>Bn(t.store)[n],set:(n,r)=>{typeof n=="string"?Object.assign(Bn(t.store),{[n]:r}):Object.assign(Bn(t.store),n),t.store.set(Bn(t.store))},store:Ds(e)};return t}globalThis.$altcha=globalThis.$altcha||{algorithms:new Map,defaults:Oi({}),i18n:Oi({}),instances:new Set,plugins:new Set};var ac={ariaLinkLabel:"Altcha (official website)",cancel:"Cancel",enterCode:"Enter code",enterCodeAria:"Enter code you hear. Press Space to play audio.",enterCodeFromImage:"To proceed, please enter the code from the image below.",error:"Verification failed. Try again later.",expired:"Verification expired. Try again.",footer:'Protected by <a href="https://altcha.org/" target="_blank" rel="noopener" aria-label="Altcha (official website, opens in new tab)">ALTCHA</a>',getAudioChallenge:"Get an audio challenge",label:"I'm not a robot",loading:"Loading...",reload:"Reload",verify:"Verify",verificationRequired:"Verification required!",verified:"Verified",verifying:"Verifying...",waitAlert:"Verifying... please wait."};globalThis.$altcha.i18n.set("en",ac);var ic="5",Bi,Ki,Yi;typeof window!="undefined"&&((Yi=(Ki=(Bi=window.__svelte)!=null?Bi:window.__svelte={}).v)!=null?Yi:Ki.v=new Set).add(ic);var Zt=Symbol("events"),Vo=new Set,ka=new Set;function jo(e,t,n,r={}){function a(i){if(r.capture||xa.call(t,i),!i.cancelBubble)return On(()=>n==null?void 0:n.call(this,i))}return e.startsWith("pointer")||e.startsWith("touch")||e==="wheel"?kt(()=>{t.addEventListener(e,a,r)}):t.addEventListener(e,a,r),a}function pe(e,t,n,r,a){var i={capture:r,passive:a},s=jo(e,t,n,i);(t===document.body||t===window||t===document||t instanceof HTMLMediaElement)&&Fr(()=>{t.removeEventListener(e,s,i)})}function jr(e,t,n){var r;((r=t[Zt])!=null?r:t[Zt]={})[e]=n}function zr(e){for(var t=0;t<e.length;t++)Vo.add(e[t]);for(var n of ka)n(e)}var ia=null,oa=!1;function xa(e){var y,O;var t=this,n=t.ownerDocument,r=e.type,a=((y=e.composedPath)==null?void 0:y.call(e))||[],i=a[0]||e.target;ia=e,oa||(oa=!0,setTimeout(()=>{oa=!1,ia=null}));var s=0,l=ia===e&&e[Zt];if(l){var c=a.indexOf(l);if(c!==-1&&(t===document||t===window)){e[Zt]=t;return}var f=a.indexOf(t);if(f===-1)return;c<=f&&(s=c)}if(i=a[s]||e.target,i!==t){Tn(e,"currentTarget",{configurable:!0,get(){return i||n}});var d=H,m=M;tt(null),Ct(null);try{for(var g,v=[];i!==null&&i!==t;){try{var b=(O=i[Zt])==null?void 0:O[r];b!=null&&(!i.disabled||e.target===i)&&b.call(i,e)}catch(P){g?v.push(P):g=P}if(e.cancelBubble)break;s++,i=s<a.length?a[s]:null}if(g){for(let P of v)queueMicrotask(()=>{throw P});throw g}}finally{e[Zt]=t,delete e.currentTarget,tt(d),Ct(m)}}}var Gi,la=((Gi=globalThis==null?void 0:globalThis.window)==null?void 0:Gi.trustedTypes)&&globalThis.window.trustedTypes.createPolicy("svelte-trusted-html",{createHTML:e=>e});function oc(e){var t;return(t=la==null?void 0:la.createHTML(e))!=null?t:e}function zo(e){var t=za("template");return t.innerHTML=oc(e.replaceAll("<!>","<!---->")),t.content}function Ge(e,t){var n=M;n.nodes===null&&(n.nodes={start:e,end:t,a:null,t:null})}function ce(e,t){var n=(t&Es)!==0,r=(t&Ss)!==0,a,i=!e.startsWith("<!>");return()=>{if(L)return Ge(D,null),D;a===void 0&&(a=zo(i?e:"<!>"+e),n||(a=Ue(a)));var s=r||oo?document.importNode(a,!0):a.cloneNode(!0);if(n){var l=Ue(s),c=s.lastChild;Ge(l,c)}else Ge(s,s);return s}}function lc(e,t,n="svg"){var r=!e.startsWith("<!>"),a=`<${n}>${r?e:"<!>"+e}</${n}>`,i;return()=>{if(L)return Ge(D,null),D;if(!i){var s=zo(a),l=Ue(s);i=Ue(l)}var c=i.cloneNode(!0);return Ge(c,c),c}}function Xa(e,t){return lc(e,t,"svg")}function wr(e=""){if(!L){var t=ft(e+"");return Ge(t,t),t}var n=D;return n.nodeType!==cr?(n.before(n=ft()),Oe(n)):Hr(n),Ge(n,n),n}function Li(){if(L)return Ge(D,null),D;var e=document.createDocumentFragment(),t=document.createComment(""),n=ft();return e.append(t,n),Ge(t,n),e}function j(e,t){if(L){var n=M;((n.f&cn)===0||n.nodes.end===null)&&(n.nodes.end=D),sn();return}e!==null&&e.before(t)}function sc(e){return e.endsWith("capture")&&e!=="gotpointercapture"&&e!=="lostpointercapture"}var cc=["beforeinput","click","change","dblclick","contextmenu","focusin","focusout","input","keydown","keyup","mousedown","mousemove","mouseout","mouseover","mouseup","pointerdown","pointermove","pointerout","pointerover","pointerup","touchend","touchmove","touchstart"];function uc(e){return cc.includes(e)}var fc={formnovalidate:"formNoValidate",ismap:"isMap",nomodule:"noModule",playsinline:"playsInline",readonly:"readOnly",defaultvalue:"defaultValue",defaultchecked:"defaultChecked",srcobject:"srcObject",novalidate:"noValidate",allowfullscreen:"allowFullscreen",disablepictureinpicture:"disablePictureInPicture",disableremoteplayback:"disableRemotePlayback"};function hc(e){var t;return e=e.toLowerCase(),(t=fc[e])!=null?t:e}var dc=["touchstart","touchmove"];function vc(e){return dc.includes(e)}var pc=jt|un;function gc(e,t,n,r){new Ea(e,t,n,r)}var Fe,nr,Ze,en,Pe,Ve,Ie,je,bt,tn,Ht,An,rr,ar,Pt,Nr,X,Bo,Ko,Sa,Yo,Ca,Wn,Sr,Aa,Ta,Ea=class{constructor(t,n,r,a){I(this,X);R(this,"parent");R(this,"is_pending",!1);R(this,"transform_error");I(this,Fe);I(this,nr,L?D:null);I(this,Ze);I(this,en);I(this,Pe);I(this,Ve,null);I(this,Ie,null);I(this,je,null);I(this,bt,null);I(this,tn,0);I(this,Ht,0);I(this,An,!1);I(this,rr,new Set);I(this,ar,new Set);I(this,Pt,null);I(this,Nr,rc(()=>(T(this,Pt,hr(h(this,tn))),()=>{T(this,Pt,null)})));var i,s;T(this,Fe,t),T(this,Ze,n),T(this,en,l=>{var c=M;c.b=this,c.f|=da,r(l)}),this.parent=M.b,this.transform_error=(s=a!=null?a:(i=this.parent)==null?void 0:i.transform_error)!=null?s:(l=>l),T(this,Pe,pr(()=>{if(L){let l=h(this,nr);sn();let c=l.data===Qi;if(l.data.startsWith(xi)){let d=JSON.parse(l.data.slice(xi.length));N(this,X,Ko).call(this,d)}else c?N(this,X,Yo).call(this):N(this,X,Bo).call(this)}else N(this,X,Ca).call(this)},pc)),L&&T(this,Fe,D)}defer_effect(t){uo(t,h(this,rr),h(this,ar))}is_rendered(){return!this.is_pending&&(!this.parent||this.parent.is_rendered())}has_pending_snippet(){return!!h(this,Ze).pending}update_pending_count(t,n){N(this,X,Aa).call(this,t,n),T(this,tn,h(this,tn)+t),!(!h(this,Pt)||h(this,An))&&(T(this,An,!0),kt(()=>{T(this,An,!1),h(this,Pt)&&Lr(h(this,Pt),h(this,tn))}))}get_effect_pending(){return h(this,Nr).call(this),o(h(this,Pt))}error(t){if(!h(this,Ze).onerror&&!h(this,Ze).failed)throw t;C!=null&&C.is_fork?(h(this,Ve)&&C.skip_effect(h(this,Ve)),h(this,Ie)&&C.skip_effect(h(this,Ie)),h(this,je)&&C.skip_effect(h(this,je)),C.oncommit(()=>{N(this,X,Ta).call(this,t)})):N(this,X,Ta).call(this,t)}};Fe=new WeakMap,nr=new WeakMap,Ze=new WeakMap,en=new WeakMap,Pe=new WeakMap,Ve=new WeakMap,Ie=new WeakMap,je=new WeakMap,bt=new WeakMap,tn=new WeakMap,Ht=new WeakMap,An=new WeakMap,rr=new WeakMap,ar=new WeakMap,Pt=new WeakMap,Nr=new WeakMap,X=new WeakSet,Bo=function(){try{T(this,Ve,ot(()=>h(this,en).call(this,h(this,Fe))))}catch(t){this.error(t)}},Ko=function(t){let n=h(this,Ze).failed,{reset:r,invoke_onerror:a}=N(this,X,Sa).call(this,t);kt(a),n&&T(this,je,ot(()=>{n(h(this,Fe),()=>t,()=>r)}))},Sa=function(t){var n=!1,r=!1;let a=()=>{if(n){Is();return}n=!0,r&&xs(),h(this,je)!==null&&Qn(h(this,je),()=>{T(this,je,null)}),N(this,X,Sr).call(this,()=>{N(this,X,Ca).call(this)})};return{reset:a,invoke_onerror:()=>{var s,l;try{r=!0,(l=(s=h(this,Ze)).onerror)==null||l.call(s,t,a),r=!1}catch(c){_t(c,h(this,Pe)&&h(this,Pe).parent)}}}},Yo=function(){let t=h(this,Ze).pending;t&&(this.is_pending=!0,T(this,Ie,ot(()=>t(h(this,Fe)))),kt(()=>{var n=T(this,bt,document.createDocumentFragment()),r=ft(),a=!1;if(n.append(r),T(this,Ve,N(this,X,Sr).call(this,()=>{try{return ot(()=>h(this,en).call(this,r))}catch(i){try{this.error(i),a=!0}catch(s){_t(s,h(this,Pe).parent)}return null}})),h(this,Ve)===null){T(this,bt,null),a&&N(this,X,Wn).call(this,C);return}h(this,Ht)===0&&(h(this,Fe).before(n),T(this,bt,null),Qn(h(this,Ie),()=>{T(this,Ie,null)}),N(this,X,Wn).call(this,C))}))},Ca=function(){try{if(this.is_pending=this.has_pending_snippet(),T(this,Ht,0),T(this,tn,0),T(this,Ve,ot(()=>{h(this,en).call(this,h(this,Fe))})),h(this,Ht)>0){var t=T(this,bt,document.createDocumentFragment());Fo(h(this,Ve),t);let n=h(this,Ze).pending;T(this,Ie,ot(()=>n(h(this,Fe))))}else N(this,X,Wn).call(this,C)}catch(n){this.error(n)}},Wn=function(t){this.is_pending=!1,t.transfer_effects(h(this,rr),h(this,ar))},Sr=function(t){var n=M,r=H,a=Ae;Ct(h(this,Pe)),tt(h(this,Pe)),Rn(h(this,Pe).ctx);try{return zt.ensure(),t()}finally{Ct(n),tt(r),Rn(a)}},Aa=function(t,n){var r;if(!this.has_pending_snippet()){this.parent&&N(r=this.parent,X,Aa).call(r,t,n);return}T(this,Ht,h(this,Ht)+t),h(this,Ht)===0&&(N(this,X,Wn).call(this,n),h(this,Ie)&&Qn(h(this,Ie),()=>{T(this,Ie,null)}),h(this,bt)&&(h(this,Fe).before(h(this,bt)),T(this,bt,null)))},Ta=function(t){h(this,Ve)&&(Ee(h(this,Ve)),T(this,Ve,null)),h(this,Ie)&&(Ee(h(this,Ie)),T(this,Ie,null)),h(this,je)&&(Ee(h(this,je)),T(this,je,null)),L&&(Oe(h(this,nr)),Va(),Oe(ja()));let n=h(this,Ze).failed,r=a=>{let{reset:i,invoke_onerror:s}=N(this,X,Sa).call(this,a);s(),n&&T(this,je,N(this,X,Sr).call(this,()=>{try{return ot(()=>{var l=M;l.b=this,l.f|=da,n(h(this,Fe),()=>a,()=>i)})}catch(l){return _t(l,h(this,Pe).parent),null}}))};kt(()=>{var a;try{a=this.transform_error(t)}catch(i){_t(i,h(this,Pe)&&h(this,Pe).parent);return}a!==null&&typeof a=="object"&&typeof a.then=="function"?a.then(r,i=>_t(i,h(this,Pe)&&h(this,Pe).parent)):r(a)})};function wt(e,t){var r;var n=t==null?"":typeof t=="object"?`${t}`:t;n!==((r=e[zn])!=null?r:e[zn]=e.nodeValue)&&(e[zn]=n,e.nodeValue=`${n}`)}function Go(e,t){return Wo(e,t)}function mc(e,t){var s;ma(),t.intro=(s=t.intro)!=null?s:!1;let n=t.target,r=L,a=D;try{for(var i=Ue(n);i&&(i.nodeType!==ur||i.data!==Ha);)i=At(i);if(!i)throw ln;xt(!0),Oe(i);let l=Wo(e,{...t,anchor:i});return xt(!1),l}catch(l){if(l instanceof Error&&l.message.split(`
`).some(c=>c.startsWith("https://svelte.dev/e/")))throw l;return l!==ln&&console.warn("Failed to hydrate: ",l),t.recover===!1&&ys(),ma(),Os(n),xt(!1),Go(e,t)}finally{xt(r),Oe(a)}}var _r=new Map;function Wo(e,{target:t,anchor:n,props:r={},events:a,context:i,intro:s=!0,transformError:l}){ma();var c=void 0,f=ec(()=>{var d=n!=null?n:t.appendChild(ft());gc(d,{pending:()=>{}},v=>{Mt({});var b=Ae;if(i&&(b.c=i),a&&(r.$$events=a),L&&Ge(v,null),c=e(v,r)||Fa(),L&&(M.nodes.end=D,D===null||D.nodeType!==ur||D.data!==eo))throw fr(),ln;Nt()},l);var m=new Set,g=v=>{for(var b=0;b<v.length;b++){var y=v[b];if(!m.has(y)){m.add(y);var O=vc(y);for(let se of[t,document]){var P=_r.get(se);P===void 0&&(P=new Map,_r.set(se,P));var z=P.get(y);z===void 0?(se.addEventListener(y,xa,{passive:O}),P.set(y,1)):P.set(y,z+1)}}}};return g(rs(Vo)),ka.add(g),()=>{var O;for(var v of m)for(let P of[t,document]){var b=_r.get(P),y=b.get(v);--y==0?(P.removeEventListener(v,xa),b.delete(v),b.size===0&&_r.delete(P)):b.set(v,y)}ka.delete(g),d!==n&&((O=d.parentNode)==null||O.removeChild(d))}});return $a.set(c,f),c}var $a=new WeakMap;function bc(e,t){let n=$a.get(e);return n?($a.delete(e),n(t)):Promise.resolve()}var it,yt,ze,nn,ir,or,Dr,In=class{constructor(t,n=!0){R(this,"anchor");I(this,it,new Map);I(this,yt,new Map);I(this,ze,new Map);I(this,nn,new Set);I(this,ir,!0);I(this,or,t=>{if(h(this,it).has(t)){var n=h(this,it).get(t),r=h(this,yt).get(n);if(r)Ii(r),h(this,nn).delete(n);else{var a=h(this,ze).get(n);a&&(Ii(a.effect),h(this,yt).set(n,a.effect),h(this,ze).delete(n),a.fragment.lastChild.remove(),this.anchor.before(a.fragment),r=a.effect)}for(let[i,s]of h(this,it)){if(h(this,it).delete(i),i===t)break;let l=h(this,ze).get(s);l&&(Ee(l.effect),h(this,ze).delete(s))}for(let[i,s]of h(this,yt)){if(i===n||h(this,nn).has(i))continue;let l=()=>{if(Array.from(h(this,it).values()).includes(i)){var f=document.createDocumentFragment();Fo(s,f),f.append(ft()),h(this,ze).set(i,{effect:s,fragment:f})}else Ee(s);h(this,nn).delete(i),h(this,yt).delete(i)};h(this,ir)||!r?(h(this,nn).add(i),Qn(s,l,!1)):l()}}});I(this,Dr,t=>{h(this,it).delete(t);let n=Array.from(h(this,it).values());for(let[r,a]of h(this,ze))n.includes(r)||(Ee(a.effect),h(this,ze).delete(r))});this.anchor=t,T(this,ir,n)}ensure(t,n){var r=C,a=Ls();if(n&&!h(this,yt).has(t)&&!h(this,ze).has(t))if(a){var i=document.createDocumentFragment(),s=ft();i.append(s),h(this,ze).set(t,{effect:ot(()=>n(s)),fragment:i})}else h(this,yt).set(t,ot(()=>n(this.anchor)));if(h(this,it).set(r,t),a){for(let[l,c]of h(this,yt))l===t?r.unskip_effect(c):r.skip_effect(c);for(let[l,c]of h(this,ze))l===t?r.unskip_effect(c.effect):r.skip_effect(c.effect);r.oncommit(h(this,or)),r.ondiscard(h(this,Dr))}else L&&(this.anchor=D),h(this,or).call(this,r)}};it=new WeakMap,yt=new WeakMap,ze=new WeakMap,nn=new WeakMap,ir=new WeakMap,or=new WeakMap,Dr=new WeakMap;function yc(e,t,...n){var r=new In(e);pr(()=>{var i;let a=(i=t())!=null?i:null;r.ensure(a,a&&(s=>a(s,...n)))},jt)}function Qa(e){Ae===null&&ds(),Ne(()=>{let t=vr(e);if(typeof t=="function")return t})}function ve(e,t,n=!1){var r;L&&(r=D,sn());var a=new In(e),i=n?jt:0;function s(l,c){if(L){var f=ao(r);if(l!==parseInt(f.substring(1))){var d=ja();Oe(d),a.anchor=d,xt(!1),a.ensure(l,c),xt(!0);return}}a.ensure(l,c)}pr(()=>{var l=!1;t((c,f=0)=>{l=!0,s(f,c)}),l||s(-1,null)},i)}var wc=Symbol("NaN");function _c(e,t,n){L&&sn();var r=new In(e);pr(()=>{var a=t();a!==a&&(a=wc),r.ensure(a,n)})}function qo(e,t,n=!1,r=!1,a=!1,i=!1){var s=e,l="";if(n){var c=e;L&&(s=Oe(Ue(c)))}Ce(()=>{var y;var f=M;if(l===(l=(y=t())!=null?y:"")){L&&sn();return}if(n&&!L){f.nodes=null,c.innerHTML=l,l!==""&&Ge(Ue(c),c.lastChild);return}if(f.nodes!==null&&(No(f.nodes.start,f.nodes.end),f.nodes=null),l!==""){if(L){D.data;for(var d=sn(),m=d;d!==null&&(d.nodeType!==ur||d.data!=="");)m=d,d=At(d);if(d===null)throw fr(),ln;Ge(D,m),s=Oe(d);return}var g=r?Cs:a?As:void 0,v=za(r?"svg":a?"math":"template",g);v.innerHTML=l;var b=r||a?v:v.content;if(Ge(Ue(b),b.lastChild),r||a)for(;Ue(b);)s.before(Ue(b));else s.before(b)}})}function kc(e,t,n){var r;L&&(r=D,sn());var a=new In(e);pr(()=>{var d;var i=(d=t())!=null?d:null;if(L){var s=ao(r),l=s===Ha,c=i!==null;if(l!==c){var f=ja();Oe(f),a.anchor=f,xt(!1),a.ensure(i,i&&(m=>n(m,i))),xt(!0);return}}a.ensure(i,i&&(m=>n(m,i)))},jt)}function xc(e,t){var n=void 0,r;Lo(()=>{n!==(n=t())&&(r&&(Ee(r),r=null),n&&(r=ot(()=>{Ja(()=>n(e))})))})}function Jo(e){var t,n,r="";if(typeof e=="string"||typeof e=="number")r+=e;else if(typeof e=="object")if(Array.isArray(e)){var a=e.length;for(t=0;t<a;t++)e[t]&&(n=Jo(e[t]))&&(r&&(r+=" "),r+=n)}else for(n in e)e[n]&&(r&&(r+=" "),r+=n);return r}function Ec(){for(var e,t,n=0,r="",a=arguments.length;n<a;n++)(e=arguments[n])&&(t=Jo(e))&&(r&&(r+=" "),r+=t);return r}function Sc(e){return typeof e=="object"?Ec(e):e!=null?e:""}var Mi=[...` 	
\r\f\xA0\v\uFEFF`];function Cc(e,t,n){var r=e==null?"":""+e;if(n){for(var a of Object.keys(n))if(n[a])r=r?r+" "+a:a;else if(r.length)for(var i=a.length,s=0;(s=r.indexOf(a,s))>=0;){var l=s+i;(s===0||Mi.includes(r[s-1]))&&(l===r.length||Mi.includes(r[l]))?r=(s===0?"":r.substring(0,s))+r.substring(l+1):s=l}}return r===""?null:r}function Ni(e,t=!1){var n=t?" !important;":";",r="";for(var a of Object.keys(e)){var i=e[a];i!=null&&i!==""&&(r+=" "+a+": "+i+n)}return r}function sa(e){return e[0]!=="-"||e[1]!=="-"?e.toLowerCase():e}function Ac(e,t){if(t){var n="",r,a;if(Array.isArray(t)?(r=t[0],a=t[1]):r=t,e){e=String(e).replaceAll(/\/\*.*?\*\//g,"").trim();var i=!1,s=0,l=!1,c=[];r&&c.push(...Object.keys(r).map(sa)),a&&c.push(...Object.keys(a).map(sa));var f=0,d=-1;let y=e.length;for(var m=0;m<y;m++){var g=e[m];if(l?g==="/"&&e[m-1]==="*"&&(l=!1):i?i===g&&(i=!1):g==="/"&&e[m+1]==="*"?l=!0:g==='"'||g==="'"?i=g:g==="("?s++:g===")"&&s--,!l&&i===!1&&s===0){if(g===":"&&d===-1)d=m;else if(g===";"||m===y-1){if(d!==-1){var v=sa(e.substring(f,d).trim());if(!c.includes(v)){g!==";"&&m++;var b=e.substring(f,m).trim();n+=" "+b+";"}}f=m+1,d=-1}}}}return r&&(n+=Ni(r)),a&&(n+=Ni(a,!0)),n=n.trim(),n===""?null:n}return e==null?null:String(e)}function Tc(e,t,n,r,a,i){var s=e[va];if(L||s!==n||s===void 0){var l=Cc(n,r,i);(!L||l!==e.getAttribute("class"))&&(l==null?e.removeAttribute("class"):t?e.className=l:e.setAttribute("class",l)),e[va]=n}else if(i&&a!==i)for(var c in i){var f=!!i[c];(a==null||f!==!!a[c])&&e.classList.toggle(c,f)}return i}function ca(e,t={},n,r){for(var a in n){var i=n[a];t[a]!==i&&(n[a]==null?e.style.removeProperty(a):e.style.setProperty(a,i,r))}}function $c(e,t,n,r){var a=e[pa];if(L||a!==t){var i=Ac(t,r);(!L||i!==e.getAttribute("style"))&&(i==null?e.removeAttribute("style"):e.style.cssText=i),e[pa]=t}else r&&(Array.isArray(r)?(ca(e,n==null?void 0:n[0],r[0]),ca(e,n==null?void 0:n[1],r[1],"important")):ca(e,n,r));return r}function Zo(e,t){t?e.hasAttribute("selected")||e.setAttribute("selected",""):e.removeAttribute("selected")}function Di(e,t){var n=!("__defaultValue"in e);!n&&e.__defaultValue===t||(e.__defaultValue=t,Xo(e,!n||"__value"in e))}function Xo(e,t){var n=e.__defaultValue,r=e.multiple,a=r?n!=null?n:[]:null;if(!(r&&!Na(a))){var i=e.selectedIndex,s=t&&r?new Set(e.selectedOptions):null;for(var l of e.options){var c=Pa(l);Zo(l,r?a.includes(c):io(c,n))}if(t)if(s!==null)for(l of e.options){var f=s.has(l);l.selected!==f&&(l.selected=f)}else e.selectedIndex!==i&&(e.selectedIndex=i)}}function Ra(e,t,n=!1){if(e.multiple){if(t==null)return;if(!Na(t))return Ps();for(var r of e.options)r.selected=t.includes(Pa(r));return}for(r of e.options){var a=Pa(r);if(io(a,t)){r.selected=!0;return}}(!n||t!==void 0)&&(e.selectedIndex=-1)}function Rc(e){var t=new MutationObserver(n=>{n.every(Pc)||("__defaultValue"in e&&Xo(e,!1),"__value"in e&&Ra(e,e.__value))});t.observe(e,{childList:!0,subtree:!0,attributes:!0,attributeFilter:["value"]}),Fr(()=>{t.disconnect()})}function Pa(e){return"__value"in e?e.__value:e.value}function Pc(e){if(e.target.closest("selectedcontent")!==null)return!0;if(e.type==="childList"){var t=[...e.addedNodes,...e.removedNodes];return t.length>0&&t.every(n=>n.nodeName==="SELECTEDCONTENT")}return!1}var Vn=Symbol("class"),jn=Symbol("style"),Qo=Symbol("is custom element"),el=Symbol("is html"),Ic=sr?"link":"LINK",Ui=sr?"input":"INPUT",Oc=sr?"option":"OPTION",tl=sr?"select":"SELECT",Lc=sr?"progress":"PROGRESS";function ei(e){if(L){var t=!1,n=()=>{if(!t){if(t=!0,e.hasAttribute("value")){var r=e.value;K(e,"value",null),e.value=r}if(e.hasAttribute("checked")){var a=e.checked;K(e,"checked",null),e.checked=a}}};e[qn]=n,kt(n),ho()}}function Mc(e,t){var n=ti(e);n.value===(n.value=t!=null?t:void 0)||e.value===t&&(t!==0||e.nodeName!==Lc)||(e.value=t!=null?t:"")}function K(e,t,n,r){var a=ti(e);L&&(a[t]=e.getAttribute(t),t==="src"||t==="srcset"||t==="href"&&e.nodeName===Ic)||a[t]!==(a[t]=n)&&(t==="loading"&&(e[fs]=n),n==null?e.removeAttribute(t):typeof n!="string"&&nl(e).has(t)?e[t]=n:e.setAttribute(t,n))}function Nc(e,t,n,r,a=!1,i=!1){var J;L&&a&&e.nodeName===Ui&&("defaultValue"in n||"defaultChecked"in n||ei(e));var s=ti(e),l=s[Qo],c=!s[el];let f=L&&l;f&&xt(!1);var d=t||{},m=e.nodeName===Oc,g=e.nodeName===tl;for(var v in t)!(v in n)&&v[0]+v[1]!=="$$"&&(n[v]=null);n.class?n.class=Sc(n.class):n[Vn]&&(n.class=null),n[jn]&&((J=n.style)!=null||(n.style=null));var b=nl(e);if(e.nodeName===Ui&&"type"in n&&("value"in n||"__value"in n)){var y=n.type;(y!==d.type||y===void 0&&e.hasAttribute("type"))&&(d.type=y,K(e,"type",y))}for(let F in n){let B=n[F];if(m&&F==="value"&&B==null){e.value=e.__value="",d[F]=B;continue}if(F==="class"){var O=e.namespaceURI==="http://www.w3.org/1999/xhtml";Tc(e,O,B,r,t==null?void 0:t[Vn],n[Vn]),d[F]=B,d[Vn]=n[Vn];continue}if(F==="style"){$c(e,B,t==null?void 0:t[jn],n[jn]),d[F]=B,d[jn]=n[jn];continue}var P=d[F];if(!(B===P&&!(B===void 0&&e.hasAttribute(F)))){d[F]=B;var z=F[0]+F[1];if(z!=="$$")if(z==="on"){let U={},ae="$$"+F,ne=F.slice(2);var se=uc(ne);if(sc(ne)&&(ne=ne.slice(0,-7),U.capture=!0),!se&&P){if(B!=null)continue;e.removeEventListener(ne,d[ae],U),d[ae]=null}if(se)jr(ne,e,B),zr([ne]);else if(B!=null){let _e=function(Le){d[F].call(this,Le)};var de=_e;d[ae]=jo(ne,e,_e,U)}}else if(F==="style")K(e,F,B);else if(F==="autofocus")Hs(e,!!B);else if(!l&&(F==="__value"||F==="value"&&B!=null))e.value=e.__value=B;else if(F==="selected"&&m)Zo(e,B);else{var q=F;c||(q=hc(q));var dt=q==="defaultValue"||q==="defaultChecked";if(g&&q==="defaultValue")continue;if(B==null&&!l&&!dt)if(s[F]=null,q==="value"||q==="checked"){let U=e,ae=t===void 0;if(q==="value"){let ne=U.defaultValue;U.removeAttribute(q),U.defaultValue=ne,U.value=U.__value=ae?ne:null}else{let ne=U.defaultChecked;U.removeAttribute(q),U.defaultChecked=ne,U.checked=ae?ne:!1}}else e.removeAttribute(F);else dt||(l||typeof B!="string")&&b.has(q)?(e[q]=B,q in s&&(s[q]=fe)):typeof B!="function"&&K(e,q,B)}}}return f&&xt(!0),d}function Br(e,t,n=[],r=[],a=[],i,s=!1,l=!1){vo(a,n,r,c=>{var f=void 0,d={},m=e.nodeName===tl,g=!1;if(Lo(()=>{var b=t(...c.map(o)),y=Nc(e,f,b,i,s,l);if(g&&m){var O=e;"defaultValue"in b&&Di(O,b.defaultValue),"value"in b&&Ra(O,b.value)}for(let z of Object.getOwnPropertySymbols(d))b[z]||Ee(d[z]);for(let z of Object.getOwnPropertySymbols(b)){var P=b[z];z.description===Ts&&(!f||P!==f[z])&&(d[z]&&Ee(d[z]),d[z]=ot(()=>xc(e,()=>P))),y[z]=P}f=y}),m){var v=e;Ja(()=>{var b=f;"defaultValue"in b&&Di(v,b.defaultValue),Ra(v,b.value,!0),Rc(v)})}g=!0})}function ti(e){var t;return(t=e[kr])!=null?t:e[kr]={[Qo]:e.nodeName.includes("-"),[el]:e.namespaceURI===to}}var Hi=new Map;function nl(e){var t=e.getAttribute("is")||e.nodeName,n=Hi.get(t);if(n)return n;Hi.set(t,n=new Set);for(var r,a=e,i=Element.prototype;i!==a;){r=as(a);for(var s in r)r[s].set&&s!=="innerHTML"&&s!=="textContent"&&s!=="innerText"&&n.add(s);a=Wi(a)}return n}function Dc(e,t,n=t){var r=new WeakSet;Fs(e,"input",async a=>{var i=a?e.defaultValue:e.value;if(i=ua(e)?fa(i):i,n(i),C!==null&&r.add(C),await Jt(),i!==(i=t())){var s=e.selectionStart,l=e.selectionEnd,c=e.value.length;if(e.value=i!=null?i:"",l!==null){var f=e.value.length;s===l&&l===c&&f>c?(e.selectionStart=f,e.selectionEnd=f):(e.selectionStart=s,e.selectionEnd=Math.min(l,f))}}}),(L&&e.defaultValue!==e.value||vr(t)==null&&e.value)&&(n(ua(e)?fa(e.value):e.value),C!==null&&r.add(C)),Vr(()=>{var a=t();if(e===document.activeElement){var i=C;if(r.has(i))return}ua(e)&&a===fa(e.value)||e.type==="date"&&!a&&!e.value||a!==e.value&&(e.value=a!=null?a:"")})}function ua(e){var t=e.type;return t==="number"||t==="range"}function fa(e){return e===""?null:+e}function ha(e,t){return e===t||(e==null?void 0:e[wn])===t}function Bt(e=Fa(),t,n,r){var a=Ae.r,i=M;return Ja(()=>{var s,l;return Vr(()=>{s=l,l=[],vr(()=>{ha(n(...l),e)||(t(e,...l),s&&ha(n(...s),e)&&t(null,...s))})}),()=>{let c=i;for(;c!==a&&c.parent!==null&&c.parent.f&$r;)c=c.parent;let f=()=>{l&&ha(n(...l),e)&&t(null,...l)},d=c.teardown;c.teardown=()=>{f(),d==null||d()}}}),e}var Uc={get(e,t){if(!e.exclude.has(t))return e.props[t]},set(e,t){return!1},getOwnPropertyDescriptor(e,t){if(!e.exclude.has(t)&&t in e.props)return{enumerable:!0,configurable:!0,value:e.props[t]}},has(e,t){return e.exclude.has(t)?!1:t in e.props},ownKeys(e){return Reflect.ownKeys(e.props).filter(t=>!e.exclude.has(t))}};function Kr(e,t,n){return new Proxy({props:e,exclude:t},Uc)}function le(e,t,n,r){var a=r,i=!0,s=()=>(i&&(i=!1,a=r),a),l;l=e[t],l===void 0&&r!==void 0&&(l=s());var c;c=()=>{var g=e[t];return g===void 0?s():(i=!0,g)};var f=!1,d=Ka(()=>(f=!1,c())),m=M;return(function(g,v){if(arguments.length>0){let b=v?o(d):g;return S(d,b),f=!0,a!==void 0&&(a=b),g}return Lt&&f||(m.f&Ye)!==0?d.v:o(d)})}function Hc(e){return new Ia(e)}var It,Xe,Ia=class{constructor(t){I(this,It);I(this,Xe);var i,s;var n=new Map,r=(l,c)=>{var f=Eo(c,!1,!1);return n.set(l,f),f};let a=new Proxy({...t.props||{},$$events:{}},{get(l,c){var f;return o((f=n.get(c))!=null?f:r(c,Reflect.get(l,c)))},has(l,c){var f;return c===us?!0:(o((f=n.get(c))!=null?f:r(c,Reflect.get(l,c))),Reflect.has(l,c))},set(l,c,f){var d;return S((d=n.get(c))!=null?d:r(c,f),f),Reflect.set(l,c,f)}});T(this,Xe,(t.hydrate?mc:Go)(t.component,{target:t.target,anchor:t.anchor,props:a,context:t.context,intro:(i=t.intro)!=null?i:!1,recover:t.recover,transformError:t.transformError})),(!((s=t==null?void 0:t.props)!=null&&s.$$host)||t.sync===!1)&&te(),T(this,It,a.$$events);for(let l of Object.keys(h(this,Xe)))l==="$set"||l==="$destroy"||l==="$on"||Tn(this,l,{get(){return h(this,Xe)[l]},set(c){h(this,Xe)[l]=c},enumerable:!0});h(this,Xe).$set=l=>{Object.assign(a,l)},h(this,Xe).$destroy=()=>{bc(h(this,Xe))}}$set(t){h(this,Xe).$set(t)}$on(t,n){h(this,It)[t]=h(this,It)[t]||[];let r=(...a)=>n.call(this,...a);return h(this,It)[t].push(r),()=>{h(this,It)[t]=h(this,It)[t].filter(a=>a!==r)}}$destroy(){h(this,Xe).$destroy()}};It=new WeakMap,Xe=new WeakMap;var rl=class{};typeof HTMLElement=="function"&&(rl=class extends HTMLElement{constructor(t,n,r){super();R(this,"$$ctor");R(this,"$$s");R(this,"$$c");R(this,"$$cn",!1);R(this,"$$d",{});R(this,"$$r",!1);R(this,"$$p_d",{});R(this,"$$l",{});R(this,"$$l_u",new Map);R(this,"$$me");R(this,"$$shadowRoot",null);this.$$ctor=t,this.$$s=n,r&&(this.$$shadowRoot=this.attachShadow(r))}addEventListener(t,n,r){if(this.$$l[t]=this.$$l[t]||[],this.$$l[t].push(n),this.$$c){let a=this.$$c.$on(t,n);this.$$l_u.set(n,a)}super.addEventListener(t,n,r)}removeEventListener(t,n,r){if(super.removeEventListener(t,n,r),this.$$c){let a=this.$$l_u.get(n);a&&(a(),this.$$l_u.delete(n))}}async connectedCallback(){if(this.$$cn=!0,!this.$$c){let n=function(i){return s=>{let l=za("slot");i!=="default"&&(l.name=i),j(s,l)}};var t=n;if(await Promise.resolve(),!this.$$cn||this.$$c)return;let r={},a=Fc(this);for(let i of this.$$s)i in a&&(i==="default"&&!this.$$d.children?(this.$$d.children=n(i),r.default=!0):r[i]=n(i));for(let i of this.attributes){let s=this.$$g_p(i.name);s in this.$$d||(this.$$d[s]=Cr(s,i.value,this.$$p_d,"toProp"))}for(let i in this.$$p_d)!(i in this.$$d)&&this[i]!==void 0&&(this.$$d[i]=this[i],delete this[i]);this.$$c=Hc({component:this.$$ctor,target:this.$$shadowRoot||this,props:{...this.$$d,$$slots:r,$$host:this}}),this.$$me=Qs(()=>{Vr(()=>{var i;this.$$r=!0;for(let s of Tr(this.$$c)){if(!((i=this.$$p_d[s])!=null&&i.reflect))continue;this.$$d[s]=this.$$c[s];let l=Cr(s,this.$$d[s],this.$$p_d,"toAttribute");l==null?this.removeAttribute(this.$$p_d[s].attribute||s):this.setAttribute(this.$$p_d[s].attribute||s,l)}this.$$r=!1})});for(let i in this.$$l)for(let s of this.$$l[i]){let l=this.$$c.$on(i,s);this.$$l_u.set(s,l)}this.$$l={}}}attributeChangedCallback(t,n,r){var a;this.$$r||(t=this.$$g_p(t),this.$$d[t]=Cr(t,r,this.$$p_d,"toProp"),(a=this.$$c)==null||a.$set({[t]:this.$$d[t]}))}disconnectedCallback(){this.$$cn=!1,Promise.resolve().then(()=>{!this.$$cn&&this.$$c&&(this.$$c.$destroy(),this.$$me(),this.$$c=void 0)})}$$g_p(t){return Tr(this.$$p_d).find(n=>this.$$p_d[n].attribute===t||!this.$$p_d[n].attribute&&n.toLowerCase()===t)||t}});function Cr(e,t,n,r){var i;let a=(i=n[e])==null?void 0:i.type;if(t=a==="Boolean"&&typeof t!="boolean"?t!=null:t,!r||!n[e])return t;if(r==="toAttribute")switch(a){case"Object":case"Array":return t==null?null:JSON.stringify(t);case"Boolean":return t?"":null;case"Number":return t==null?null:t;default:return t}else switch(a){case"Object":case"Array":return t&&JSON.parse(t);case"Boolean":return t;case"Number":return t!=null?+t:t;default:return t}}function Fc(e){let t={};return e.childNodes.forEach(n=>{t[n.slot||"default"]=!0}),t}function Kt(e,t,n,r,a,i){let s=class extends rl{constructor(){super(e,n,a),this.$$p_d=t}static get observedAttributes(){return Tr(t).map(l=>(t[l].attribute||l).toLowerCase())}};return Tr(t).forEach(l=>{Tn(s.prototype,l,{get(){return this.$$c&&l in this.$$c?this.$$c[l]:this.$$d[l]},set(c){var m;c=Cr(l,c,t),this.$$d[l]=c;var f=this.$$c;if(f){var d=(m=bn(f,l))==null?void 0:m.get;d?f[l]=c:f.$set({[l]:c})}}})}),r.forEach(l=>{Tn(s.prototype,l,{get(){var c;return(c=this.$$c)==null?void 0:c[l]}})}),e.element=s,s}var Vc=new Set(["$$slots","$$events","$$legacy","$$host","loading"]),jc=ce('<div class="altcha-checkbox"><input/> <svg aria-hidden="true" width="12" height="9" viewBox="0 0 12 9"><polyline points="1 5 4 8 11 1"></polyline></svg> <div class="altcha-spinner altcha-checkbox-spinner" aria-hidden="true"></div></div>');function al(e,t){Mt(t,!0);let n=le(t,"loading"),r=Kr(t,Vc),a;function i(){a==null||a.click()}var s={get loading(){return n()},set loading(d){n(d),te()}},l=jc(),c=ye(l);Br(c,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),Bt(c,d=>a=d,()=>a);var f=oe(c,2);return Va(2),he(l),Ce(()=>K(l,"data-loading",n())),jr("click",f,i),j(e,l),Nt(s)}zr(["click"]);Kt(al,{loading:{}},[],[],{mode:"open"});var zc=new Set(["$$slots","$$events","$$legacy","$$host","loading"]),Bc=ce('<div class="altcha-checkbox-native"><input/> <div class="altcha-spinner altcha-checkbox-native-spinner"></div></div>');function il(e,t){Mt(t,!0);let n=le(t,"loading"),r=Kr(t,zc);var a={get loading(){return n()},set loading(l){n(l),te()}},i=Bc(),s=ye(i);return Br(s,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),Va(2),he(i),Ce(()=>K(i,"data-loading",n())),j(e,i),Nt(a)}Kt(il,{loading:{}},[],[],{mode:"open"});var Kc=ce('<div><a target="_blank" rel="noopener" class="altcha-logo" aria-hidden="true" tabindex="-1"><svg width="22" height="22" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.33955 16.4279C5.88954 20.6586 12.1971 21.2105 16.4279 17.6604C18.4699 15.947 19.6548 13.5911 19.9352 11.1365L17.9886 10.4279C17.8738 12.5624 16.909 14.6459 15.1423 16.1284C11.7577 18.9684 6.71167 18.5269 3.87164 15.1423C1.03163 11.7577 1.4731 6.71166 4.8577 3.87164C8.24231 1.03162 13.2883 1.4731 16.1284 4.8577C16.9767 5.86872 17.5322 7.02798 17.804 8.2324L19.9522 9.01429C19.7622 7.07737 19.0059 5.17558 17.6604 3.57212C14.1104 -0.658624 7.80283 -1.21043 3.57212 2.33956C-0.658625 5.88958 -1.21046 12.1971 2.33955 16.4279Z" fill="currentColor"></path><path d="M3.57212 2.33956C1.65755 3.94607 0.496389 6.11731 0.12782 8.40523L2.04639 9.13961C2.26047 7.15832 3.21057 5.25375 4.8577 3.87164C8.24231 1.03162 13.2883 1.4731 16.1284 4.8577L13.8302 6.78606L19.9633 9.13364C19.7929 7.15555 19.0335 5.20847 17.6604 3.57212C14.1104 -0.658624 7.80283 -1.21043 3.57212 2.33956Z" fill="currentColor"></path><path d="M7 10H5C5 12.7614 7.23858 15 10 15C12.7614 15 15 12.7614 15 10H13C13 11.6569 11.6569 13 10 13C8.3431 13 7 11.6569 7 10Z" fill="currentColor"></path></svg></a></div>');function ni(e,t){Mt(t,!0);let n=le(t,"strings"),r="https://altcha.org";var a={get strings(){return n()},set strings(l){n(l),te()}},i=Kc(),s=ye(i);return K(s,"href",r),he(i),Ce(()=>K(s,"aria-label",n().ariaLinkLabel)),j(e,i),Nt(a)}Kt(ni,{strings:{}},[],[],{mode:"open"});var Yc=ce('<div class="altcha-footer"><p></p> <!></div>');function Oa(e,t){Mt(t,!0);let n=le(t,"logo"),r=le(t,"strings");var a={get logo(){return n()},set logo(f){n(f),te()},get strings(){return r()},set strings(f){r(f),te()}},i=Yc(),s=ye(i);qo(s,()=>r().footer,!0),he(s);var l=oe(s,2);{var c=f=>{ni(f,{get strings(){return r()}})};ve(l,f=>{n()&&f(c)})}return he(i),j(e,i),Nt(a)}Kt(Oa,{logo:{},strings:{}},[],[],{mode:"open"});var Gc=new Set(["$$slots","$$events","$$legacy","$$host","loading"]),Wc=ce('<div class="altcha-switch"><input/>  <div class="altcha-switch-toggle"><div class="altcha-spinner altcha-switch-spinner"></div></div></div>');function ol(e,t){Mt(t,!0);let n=le(t,"loading"),r=Kr(t,Gc),a;function i(){a==null||a.click()}var s={get loading(){return n()},set loading(d){n(d),te()}},l=Wc(),c=ye(l);Br(c,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),Bt(c,d=>a=d,()=>a);var f=oe(c,2);return he(l),Ce(()=>K(l,"data-loading",n())),jr("click",f,i),j(e,l),Nt(s)}zr(["click"]);Kt(ol,{loading:{}},[],[],{mode:"open"});var ke=(e=>(e.ERROR="error",e.LOADING="loading",e.PLAYING="playing",e.PAUSED="paused",e.READY="ready",e))(ke||{}),ll=(e=>(e.SHA_256="SHA-256",e.SHA_384="SHA-384",e.SHA_512="SHA-512",e))(ll||{}),G=(e=>(e.CODE="code",e.ERROR="error",e.VERIFIED="verified",e.VERIFYING="verifying",e.UNVERIFIED="unverified",e.EXPIRED="expired",e))(G||{}),qc=ce('<div class="altcha-code-challenge-title"> </div>'),Jc=ce('<div class="altcha-spinner"></div>'),Zc=Xa('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12.8659 3.00017L22.3922 19.5002C22.6684 19.9785 22.5045 20.5901 22.0262 20.8662C21.8742 20.954 21.7017 21.0002 21.5262 21.0002H2.47363C1.92135 21.0002 1.47363 20.5525 1.47363 20.0002C1.47363 19.8246 1.51984 19.6522 1.60761 19.5002L11.1339 3.00017C11.41 2.52187 12.0216 2.358 12.4999 2.63414C12.6519 2.72191 12.7782 2.84815 12.8659 3.00017ZM10.9999 16.0002V18.0002H12.9999V16.0002H10.9999ZM10.9999 9.00017V14.0002H12.9999V9.00017H10.9999Z"></path></svg>'),Xc=Xa('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15 7C15 6.44772 15.4477 6 16 6C16.5523 6 17 6.44772 17 7V17C17 17.5523 16.5523 18 16 18C15.4477 18 15 17.5523 15 17V7ZM7 7C7 6.44772 7.44772 6 8 6C8.55228 6 9 6.44772 9 7V17C9 17.5523 8.55228 18 8 18C7.44772 18 7 17.5523 7 17V7Z"></path></svg>'),Qc=Xa('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 12H7C8.10457 12 9 12.8954 9 14V19C9 20.1046 8.10457 21 7 21H4C2.89543 21 2 20.1046 2 19V12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12V19C22 20.1046 21.1046 21 20 21H17C15.8954 21 15 20.1046 15 19V14C15 12.8954 15.8954 12 17 12H20C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12Z"></path></svg>'),eu=ce('<button type="button" class="altcha-button altcha-button-secondary"><!></button>'),tu=ce('<audio hidden="" autoplay=""></audio>'),nu=ce('<div class="altcha-code-challenge"><form data-code-challenge="true"><!> <div class="altcha-code-challenge-text"> </div> <img class="altcha-code-challenge-image" alt=""/> <div class="altcha-code-challenge-row"><input type="text" class="altcha-input" autocomplete="off" name="" required=""/> <!> <button type="button" class="altcha-button altcha-button-secondary"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12C4 9.25022 5.38734 6.82447 7.50024 5.38451L7.5 8H9.5V2L3.5 2V4L5.99918 3.99989C3.57075 5.82434 2 8.72873 2 12Z"></path></svg></button></div> <div class="altcha-code-challenge-buttons"><button type="submit" class="altcha-button"> </button> <button type="button" class="altcha-button altcha-button-secondary"> </button></div></form> <!></div>');function sl(e,t){Mt(t,!0);let n=le(t,"audioUrl"),r=le(t,"codeChallenge"),a=le(t,"config"),i=le(t,"imageUrl"),s=le(t,"onCancel"),l=le(t,"onReload"),c=le(t,"onSubmit"),f=le(t,"strings"),d=V(void 0),m=V(void 0),g=V(void 0),v=V(!1),b=V(""),y=V(!1);Qa(()=>(a().disableAutoFocus||Jt().then(()=>{var E;(E=o(g))==null||E.focus()}),()=>{o(m)&&(o(m).pause(),S(m,void 0))}));function O(){S(d,ke.PAUSED,!0)}function P(E){S(d,ke.ERROR,!0)}function z(){S(d,ke.READY,!0)}function se(){S(d,ke.LOADING,!0)}function de(){S(d,ke.PLAYING,!0)}function q(){S(d,ke.PAUSED,!0)}function dt(E){var Y;E.code==="Space"?(E.preventDefault(),E.stopPropagation(),B()):E.code==="Escape"&&(E.preventDefault(),E.stopPropagation(),(Y=s())==null||Y())}function J(E){var Y;E.preventDefault(),E.stopPropagation(),(Y=c())==null||Y(o(b))}function F(E){E.play().catch(Y=>{if(!(Y instanceof DOMException&&Y.name==="AbortError"))throw Y})}function B(){o(m)?o(d)===ke.LOADING||(o(m).paused?(n()&&o(m).src!==n()&&(o(m).src=n()),o(m).currentTime=0,F(o(m))):o(m).pause()):(S(y,!0),requestAnimationFrame(()=>{o(m)&&n()&&(o(m).src=n(),F(o(m)))}))}var U={get audioUrl(){return n()},set audioUrl(E){n(E),te()},get codeChallenge(){return r()},set codeChallenge(E){r(E),te()},get config(){return a()},set config(E){a(E),te()},get imageUrl(){return i()},set imageUrl(E){i(E),te()},get onCancel(){return s()},set onCancel(E){s(E),te()},get onReload(){return l()},set onReload(E){l(E),te()},get onSubmit(){return c()},set onSubmit(E){c(E),te()},get strings(){return f()},set strings(E){f(E),te()}},ae=nu(),ne=ye(ae),_e=ye(ne);{var Le=E=>{var Y=qc(),Gt=gn(Y,!0);Ce(()=>wt(Gt,f().verificationRequired)),j(E,Y)};ve(_e,E=>{a().codeChallengeDisplay!=="standard"&&E(Le)})}var ie=oe(_e,2),Tt=gn(ie,!0),x=oe(ie,2),be=oe(x,2),Q=ye(be);ei(Q),Q.disabled=o(v),Bt(Q,E=>S(g,E),()=>o(g));var _=oe(Q,2);{var Ln=E=>{var Y=eu(),Gt=ye(Y);{var Hn=Te=>{var pt=Jc();j(Te,pt)},Yr=Te=>{var pt=Zc();j(Te,pt)},Gr=Te=>{var pt=Xc();j(Te,pt)},Wr=Te=>{var pt=Qc();j(Te,pt)};ve(Gt,Te=>{o(d)===ke.LOADING?Te(Hn):o(d)===ke.ERROR?Te(Yr,1):o(d)===ke.PLAYING?Te(Gr,2):Te(Wr,-1)})}he(Y),Ce(()=>{K(Y,"title",f().getAudioChallenge),Y.disabled=o(d)===ke.LOADING||o(d)===ke.ERROR,K(Y,"aria-label",o(d)===ke.LOADING?f().loading:f().getAudioChallenge)}),pe("click",Y,()=>B(),!0),j(E,Y)};ve(_,E=>{r().audio&&E(Ln)})}var Mn=oe(_,2);he(be);var vt=oe(be,2),Nn=ye(vt),gr=gn(Nn,!0),Yt=oe(Nn,2),Dn=gn(Yt,!0);he(vt),he(ne);var Un=oe(ne,2);{var Me=E=>{var Y=tu();Bt(Y,Gt=>S(m,Gt),()=>o(m)),pe("error",Y,P),pe("loadstart",Y,se),pe("canplay",Y,z),pe("pause",Y,q),pe("playing",Y,de),pe("ended",Y,O),j(E,Y)};ve(Un,E=>{o(y)&&E(Me)})}return he(ae),Ce(()=>{wt(Tt,f().enterCodeFromImage),K(x,"src",i()),K(Q,"minlength",r().length||1),K(Q,"maxlength",r().length),K(Q,"placeholder",f().enterCode),K(Q,"aria-label",o(d)===ke.LOADING?f().loading:o(d)===ke.PLAYING?"":f().enterCodeAria),K(Q,"aria-live",o(d)?"assertive":"polite"),K(Q,"aria-busy",o(d)===ke.LOADING),K(Mn,"title",f().reload),K(Mn,"aria-label",f().reload),K(Nn,"aria-label",f().verify),wt(gr,f().verify),K(Yt,"aria-label",f().cancel),wt(Dn,f().cancel)}),pe("submit",ne,J,!0),jr("keydown",Q,dt),Dc(Q,()=>o(b),E=>S(b,E)),pe("click",Mn,()=>{var E;return(E=l())==null?void 0:E()},!0),pe("click",Yt,()=>{var E;return(E=s())==null?void 0:E()},!0),j(e,ae),Nt(U)}zr(["keydown"]);Kt(sl,{audioUrl:{},codeChallenge:{},config:{},imageUrl:{},onCancel:{},onReload:{},onSubmit:{},strings:{}},[],[],{mode:"open"});var ru=new Set(["$$slots","$$events","$$legacy","$$host","anchor","children","display","backdrop","onClickOutside","onClickOutsideDelay","onClose","placement","updateUISignal","variant"]),au=ce('<div class="altcha-popover-backdrop" data-backdrop=""></div>'),iu=ce('<div class="altcha-popover-arrow"></div>'),ou=ce('<div role="button" class="altcha-popover-close">&times;</div>'),lu=ce('<!> <div><!> <!> <div class="altcha-popover-content"><!></div></div>',1);function La(e,t){Mt(t,!0);let n=le(t,"anchor"),r=le(t,"children"),a=le(t,"display",7,"standard"),i=le(t,"backdrop",7,!1),s=le(t,"onClickOutside"),l=le(t,"onClickOutsideDelay",7,600),c=le(t,"onClose"),f=le(t,"placement",7,"auto"),d=le(t,"updateUISignal"),m=le(t,"variant",7,"neutral"),g=Kr(t,ru),v=V(void 0),b=V(void 0),y=V(!1),O=V(0);Ne(()=>{f()!=="auto"&&S(y,f()==="top")}),Ne(()=>{d()&&q()}),Qa(()=>{let x=a()==="bottomsheet"||a()==="overlay";return x&&(o(b)&&document.body.append(o(b)),o(v)&&document.body.append(o(v))),q(),Jt().then(()=>{S(O,Date.now(),!0)}),()=>{x&&(o(b)&&document.body.removeChild(o(b)),o(v)&&document.body.removeChild(o(v)))}});function P(){var x;(x=c())==null||x()}function z(x){var Q,_;let be=x.target;!((Q=o(v))!=null&&Q.contains(be))&&(!l()||o(O)+l()<Date.now())&&((_=s())==null||_())}function se(){q()}function de(){q()}function q(){if(n()&&f()==="auto"&&o(v)){let x=n().getBoundingClientRect(),Q=document.documentElement.clientHeight-(x.top+x.height)<o(v).clientHeight;o(y)!==Q&&S(y,Q)}}var dt={get anchor(){return n()},set anchor(x){n(x),te()},get children(){return r()},set children(x){r(x),te()},get display(){return a()},set display(x="standard"){a(x),te()},get backdrop(){return i()},set backdrop(x=!1){i(x),te()},get onClickOutside(){return s()},set onClickOutside(x){s(x),te()},get onClickOutsideDelay(){return l()},set onClickOutsideDelay(x=600){l(x),te()},get onClose(){return c()},set onClose(x){c(x),te()},get placement(){return f()},set placement(x="auto"){f(x),te()},get updateUISignal(){return d()},set updateUISignal(x){d(x),te()},get variant(){return m()},set variant(x="neutral"){m(x),te()}},J=lu();pe("click",rn,z,!0),pe("resize",rn,se),pe("scroll",rn,de);var F=vn(J);{var B=x=>{var be=au();Bt(be,Q=>S(b,Q),()=>o(b)),j(x,be)};ve(F,x=>{i()&&x(B)})}var U=oe(F,2);Br(U,()=>{var x;return{...g,class:`altcha-popover ${(x=t.class||"")!=null?x:""}`,"data-popover":!0,"data-variant":m(),"data-top":o(y),"data-display":a()}});var ae=ye(U);{var ne=x=>{var be=iu();j(x,be)};ve(ae,x=>{a()==="standard"&&x(ne)})}var _e=oe(ae,2);{var Le=x=>{var be=ou();pe("click",be,P,!0),j(x,be)};ve(_e,x=>{a()!=="standard"&&x(Le)})}var ie=oe(_e,2),Tt=ye(ie);return yc(Tt,()=>{var x;return(x=r())!=null?x:Ft}),he(ie),he(U),Bt(U,x=>S(v,x),()=>o(v)),j(e,J),Nt(dt)}Kt(La,{anchor:{},children:{},display:{},backdrop:{},onClickOutside:{},onClickOutsideDelay:{},onClose:{},placement:{},updateUISignal:{},variant:{}},[],[],{mode:"open"});function su(e){return Array.from(new Uint8Array(e)).map(t=>t.toString(16).padStart(2,"0")).join("")}function cu(e,t="altcha-css",n){var r,a,i;if(typeof document!="undefined"&&document&&!document.getElementById(t)){let s=document.createElement("style");s.id=t,s.textContent=e;let l=(i=(r=document.currentScript)==null?void 0:r.nonce)!=null?i:(a=document.querySelector('meta[name="csp-nonce"]'))==null?void 0:a.content;l&&(s.nonce=l),document.head.appendChild(s)}}Object.values(ll);async function cl(e){var g;let{challenge:t,concurrency:n=navigator.hardwareConcurrency,controller:r=new AbortController,createWorker:a,onOutOfMemory:i=v=>v>1?Math.floor(v/2):0,counterMode:s,timeout:l}=e,c=Math.min(16,Math.max(1,n)),f=[],d=()=>{for(let v of f)v.terminate()};for(let v=0;v<c;v++)f.push(await a(t.parameters.algorithm));let m=null;try{m=await Promise.race(f.map((v,b)=>(r.signal.addEventListener("abort",()=>{v.postMessage({type:"abort"})}),new Promise((y,O)=>{v.addEventListener("error",P=>{O(P)}),v.addEventListener("message",P=>{if(P.data){for(let z of f)z!==v&&z.postMessage({type:"abort"});if(P.data.error)return O(new Error(P.data.error))}y(P.data)}),v.postMessage({challenge:t,counterMode:s,counterStart:b,counterStep:c,timeout:l,type:"work"})}))))}catch(v){if(v instanceof Error&&!!((g=v==null?void 0:v.message)!=null&&g.includes("Out of memory"))&&i){d();let y=i(c);if(y)return cl({...e,challenge:t,controller:r,concurrency:y,createWorker:a})}throw v}finally{d()}return r.signal.aborted?null:m||null}var Ma=class{constructor(t={}){R(this,"TAG_CODES",{INPUT:1,TEXTAREA:2,SELECT:3,BUTTON:4,A:5,DETAILS:6,SUMMARY:7,IFRAME:8,VIDEO:9,AUDIO:10});R(this,"maxSamples");R(this,"sampleInterval");R(this,"target");R(this,"focusStartTime",0);R(this,"focusInteraction",0);R(this,"focusInteractionTimer",null);R(this,"lastPointerSample",0);R(this,"lastTouchSample",0);R(this,"lastScrollSample",0);R(this,"pendingPointer",null);R(this,"pendingTouch",null);R(this,"focus",[]);R(this,"pointer",[]);R(this,"scroll",[]);R(this,"touch",[]);R(this,"onFocus",t=>{var a;if(this.focusInteraction===2)return;let n=t.target;if(!(n instanceof Element))return;let r=performance.now();this.focusStartTime===0&&(this.focusStartTime=r),this.focus.push([Math.round(r-this.focusStartTime),n.tabIndex,(a=this.TAG_CODES[n.tagName])!=null?a:0,this.focusInteraction?1:0]),this.evict(this.focus)});R(this,"onInteraction",t=>{this.focusInteraction="keyCode"in t?1:2,this.focusInteractionTimer&&clearTimeout(this.focusInteractionTimer),this.focusInteractionTimer=setTimeout(()=>{this.focusInteraction=0},100)});R(this,"onPointer",t=>{if(t.pointerType==="touch")return;let n=t.timeStamp||performance.now();this.pendingPointer=[Math.round(t.clientX),Math.round(t.clientY),Math.round(n)],n-this.lastPointerSample>=this.sampleInterval&&(this.pointer.push(this.pendingPointer),this.lastPointerSample=n,this.pendingPointer=null,this.evict(this.pointer))});R(this,"onScroll",()=>{let t=performance.now();t-this.lastScrollSample<this.sampleInterval||(this.scroll.push([Math.round(window.scrollY),Math.round(t)]),this.lastScrollSample=t,this.evict(this.scroll))});R(this,"onTouchMove",t=>{let n=t.timeStamp||performance.now(),r=t.touches[0];r&&(this.pendingTouch=[Math.round(r.clientX),Math.round(r.clientY),Math.round(n),Math.round(r.force*1e3)/1e3,Math.round(r.radiusX||0),Math.round(r.radiusY||0)],n-this.lastTouchSample>=this.sampleInterval&&(this.touch.push(this.pendingTouch),this.lastTouchSample=n,this.pendingTouch=null,this.evict(this.touch)))});let{maxSamples:n=60,sampleInterval:r=50,target:a=window}=t;this.maxSamples=n,this.sampleInterval=r,this.target=a,this.attach()}destroy(){let t={capture:!0};this.target.removeEventListener("focusin",this.onFocus,t),this.target.removeEventListener("keydown",this.onInteraction,t),this.target.removeEventListener("pointerdown",this.onInteraction,t),this.target.removeEventListener("pointermove",this.onPointer,t),this.target.removeEventListener("scroll",this.onScroll,t),this.target.removeEventListener("touchmove",this.onTouchMove,t)}export(){return{focus:this.focus,maxTouchPoints:navigator.maxTouchPoints||0,pointer:this.pointer,scroll:this.scroll,time:Date.now(),touch:this.touch}}attach(){let t={passive:!0,capture:!0};this.target.addEventListener("focusin",this.onFocus,t),this.target.addEventListener("keydown",this.onInteraction,t),this.target.addEventListener("pointerdown",this.onInteraction,t),this.target.addEventListener("pointermove",this.onPointer,t),this.target.addEventListener("scroll",this.onScroll,t),this.target.addEventListener("touchmove",this.onTouchMove,t)}evict(t){t.length>this.maxSamples&&t.splice(0,t.length-this.maxSamples)}},uu=ce('<div class="altcha-overlay-backdrop" data-backdrop=""></div>'),fu=ce('<div class="altcha-overlay-content"></div>'),hu=ce('<div role="button" class="altcha-overlay-close">&times;</div> <!>',1),du=ce('<div class="altcha-floating-arrow"></div>'),vu=ce('<input type="hidden"/>'),pu=ce('<div class="altcha-error">Secure context (HTTPS) required.</div>'),Fi=ce('<div class="altcha-error"> </div>'),gu=ce("<!> <!>",1),mu=ce('<!> <div class="altcha"><!> <div class="altcha-main"><div><div class="altcha-checkbox-wrap"><!> <label><!></label></div> <!></div> <!> <!> <!></div> <!></div>',1);function bu(e,t){Mt(t,!0);let n=()=>Si(d,"$altchaDefaults",a),r=()=>Si(b,"$altchaI18nStore",a),[a,i]=Us(),s='input[type="text"]:not([data-no-spamfilter]), textarea:not([data-no-spamfilter])',l='input[type="submit"], button[type="submit"], button:not([type="button"]):not([type="reset"])',c=["ar","fa","he","ur"],{isSecureContext:f}=globalThis,{store:d}=globalThis.$altcha.defaults,m=navigator.hardwareConcurrency||2,g=navigator.deviceMemory||0,v=g&&g<=4?Math.min(4,m):m,b=globalThis.$altcha.i18n.store,y=t.$$host,O=(u,p)=>{Jt().then(()=>{y==null||y.dispatchEvent(new CustomEvent(u,{detail:p}))})},P=null,z=V(Ot(new URL(location.origin))),se=V(!1),de=V(null),q=V(null),dt=V(null),J=V(Ot(G.UNVERIFIED)),F=V(void 0),B=V(void 0),U=V(null),ae=V(void 0),ne=V(null),_e=V(null),Le=V(null),ie=V(null),Tt=V(Ot([])),x=V(0),be=V(Ot({})),Q=V(!0),_=Re(()=>({fetch:(u,p)=>fetch(u,p),audioChallengeLanguage:"",auto:"off",barPlacement:"bottom",challenge:"",codeChallenge:null,codeChallengeDisplay:"standard",credentials:null,debug:!1,disableAutoFocus:!1,display:"standard",floatingAnchor:"",floatingOffset:8,floatingPersist:!1,floatingPlacement:"auto",hideFooter:!1,hideLogo:!1,humanInteractionSignature:!0,language:"",mockError:!1,minDuration:500,overlayContent:"",name:"altcha",popoverPlacement:"auto",retryOnOutOfMemoryError:!0,setCookie:null,serverVerificationFields:!1,serverVerificationTimeZone:!1,test:!1,timeout:9e4,type:"checkbox",validationMessage:"",verifyFunction:null,verifyUrl:"",workers:v,...n(),...o(be)})),Ln=Re(()=>`altcha-checkbox-${t.id||Math.floor(Math.random()*1e12).toString(16)}`),Mn=Re(()=>gl(o(_).type)),vt=Re(()=>o(_).auto),Nn=Re(()=>o(J)===G.VERIFYING),gr=Re(()=>!o(_).hideFooter),Yt=Re(()=>!o(_).hideLogo&&o(_).display!=="bar"),Dn=Re(()=>ml(r(),[o(_).language,document.documentElement.lang,...navigator.languages])),Un=Re(()=>c.includes(o(Dn).language)?"rtl":void 0),Me=Re(()=>({...o(Dn).strings})),E=Re(()=>{var u,p,k;return(p=(u=o(de))==null?void 0:u.audio)!=null&&p.match(/^(https?:)?\//)?mr(o(de).audio,o(z),{language:o(_).audioChallengeLanguage||o(Dn).language}).toString():(k=o(de))==null?void 0:k.audio}),Y=Re(()=>{var u,p,k;return(p=(u=o(de))==null?void 0:u.image)!=null&&p.match(/^(https?:)?\//)?mr(o(de).image,o(z)):(k=o(de))==null?void 0:k.image});Ne(()=>{Fn({auto:t.auto,challenge:t.challenge,display:t.display,language:t.language,name:t.name,type:t.type,workers:t.workers})}),Ne(()=>{t.theme?y==null||y.setAttribute("theme",t.theme):y==null||y.removeAttribute("theme")}),Ne(()=>{if(t.configuration)try{Fn(JSON.parse(t.configuration))}catch(u){ee("unable to parse the `configuration` attribute (JSON expected)")}}),Ne(()=>{o(dt)!==o(_).display&&br(o(_).display)}),Ne(()=>{o(se)&&o(J)===G.VERIFYING&&S(se,!1)}),Ne(()=>{!o(se)&&o(J)===G.VERIFIED&&S(se,!0)}),Ne(()=>{if(!o(se)){let u=qr();u&&u.checked&&(u.checked=!1)}}),Ne(()=>{var u;o(J)===G.VERIFIED&&((u=qr())==null||u.setCustomValidity(""))}),Ne(()=>{if(o(vt)==="onload"){let u=setTimeout(()=>{fn()},10);return()=>{u&&clearTimeout(u)}}}),Ne(()=>{o(_e)&&ee("error:",o(_e))}),Ne(()=>{o(ie)&&o(_).setCookie&&Il(o(ie),o(_).setCookie)}),Qa(()=>{var u,p;return ee("mounted","3.5.0"),y&&globalThis.$altcha.instances.add(y),S(U,yl(),!0),(u=o(U))==null||u.addEventListener("reset",si),(p=o(U))==null||p.addEventListener("submit",ci,{capture:!0}),document.addEventListener("focusin",li),Gt(),o(_).humanInteractionSignature&&(ee("human interaction signature enabled"),P=new Ma),O("load"),f||ee("secure context (HTTPS) required"),()=>{var k,w;Yr(),y&&globalThis.$altcha.instances.delete(y),o(Le)&&clearTimeout(o(Le)),(k=o(U))==null||k.removeEventListener("reset",si),(w=o(U))==null||w.removeEventListener("submit",ci,{capture:!0}),document.removeEventListener("focusin",li),P==null||P.destroy()}});function Gt(){S(Tt,[...globalThis.$altcha.plugins].map(u=>new u(y)),!0),ee("activating plugins",o(Tt).map(u=>u.constructor.name));for(let u of o(Tt))u.activate()}async function Hn(u,...p){let k;for(let w of o(Tt))k=await w[u].call(w,...p);return k}function Yr(){for(let u of o(Tt))u.destroy()}function Gr(u){let[p,k]=u.salt.split("?"),w={};if(k)try{Object.assign(w,Object.fromEntries(new URLSearchParams(k).entries()))}catch($){}let A={codeChallenge:u.codeChallenge,parameters:{algorithm:u.algorithm,cost:1,data:w,expiresAt:w!=null&&w.expires?parseInt(w.expires,10):void 0,keyLength:u.algorithm==="SHA-512"?64:u.algorithm==="SHA-384"?48:32,nonce:su(new TextEncoder().encode(u.salt)),keyPrefix:u.challenge,salt:""},signature:u.signature};return Object.defineProperties(A,{_originalSalt:{enumerable:!1,value:u.salt,writable:!1},_version:{enumerable:!1,value:1,writable:!1}}),A}function Wr(u,p){return{algorithm:u.parameters.algorithm,challenge:u.parameters.keyPrefix,number:p.counter,salt:"_originalSalt"in u?u._originalSalt:u.parameters.nonce,signature:u.signature,took:p.time||0}}async function Te(u){await new Promise(p=>setTimeout(p,u))}async function pt(u=o(_).challenge,p){let k=await Hn("onFetchChallenge",u),w=null;if(k!==void 0)return k;if(typeof u=="string")if(u.startsWith("{")){ee("parsing JSON challenge");try{w=JSON.parse(u)}catch(A){throw new Error("Unable to parse JSON challenge.")}}else{ee("fetching challenge from",(p==null?void 0:p.method)||"GET",u),S(z,new URL(u,location.origin),!0);let A=await o(_).fetch(u,{credentials:o(_).credentials||void 0,...p});await fi(A);let $=A.headers.get("x-altcha-config");$&&$l($);let W=await A.json();if(W&&"his"in W&&W.his){if(ee("requested HIS"),!P)throw new Error("Server requested HIS data but collector is disabled.");return pt(mr(W.his.url,o(z)),{body:JSON.stringify({his:P.export()}),headers:{"content-type":"application/json"},method:"POST"})}W&&"hisResult"in W&&W.hisResult&&ee("HIS result",W.hisResult),w=W}else if(u&&typeof u=="object")try{w=JSON.parse(JSON.stringify(u))}catch(A){throw new Error("Unable to parse JSON challenge.")}if(vl(w)&&(w=Gr(w)),!pl(w))throw new Error("Challenge validation failed.");return w}function vl(u){return u!==null&&typeof u=="object"&&"challenge"in u}function pl(u){return!!u&&typeof u=="object"&&"parameters"in u&&!!u.parameters&&typeof u.parameters=="object"&&"algorithm"in u.parameters&&"nonce"in u.parameters&&"salt"in u.parameters&&"keyPrefix"in u.parameters}function qr(){return document.getElementById(o(Ln))}function gl(u){switch(u){case"checkbox":return al;case"switch":return ol;default:return il}}function ml(u,p){let k=Object.keys(u).map(A=>A.toLowerCase()),w=p.reduce((A,$)=>($=$.toLowerCase(),A||(u[$]?$:null)||k.find(W=>$.split("-")[0]===W.split("-")[0])||null),null);return u[w||""]||(w="en"),{language:w,strings:u[w]}}function bl(u){switch(u){case"bar":return o(_).barPlacement||"bottom";case"floating":return o(_).floatingPlacement||"auto";default:return}}function yl(){var u,p,k;if(t.form){let w=y==null?void 0:y.getRootNode(),A=(u=w==null?void 0:w.getElementById)==null?void 0:u.call(w,t.form);return A instanceof HTMLFormElement?A:null}return(k=(p=o(ae))==null?void 0:p.closest("form"))!=null?k:null}function oi(u){return o(U)?Array.from(o(U).elements).filter(p=>p.matches(u)):[]}function wl(u){return oi(s).reduce((k,w)=>{let A=w.name,$=w.value;return A&&$&&(k[A]=/\n/.test($)?$.replace(new RegExp("(?<!\\r)\\n","g"),`\r
`):$),k},{})}function _l(){try{return Intl.DateTimeFormat().resolvedOptions().timeZone}catch(u){}}function mr(u,p,k){let w=new URL(u,p);if(w.search||(w.search=p.search),k)for(let A in k)k[A]!==void 0&&k[A]!==null&&w.searchParams.set(A,k[A]);return w.toString()}function kl(u){!o(se)&&u.currentTarget.checked?(u.preventDefault(),u.currentTarget.checked=!1,o(J)!==G.VERIFYING&&fn()):u.currentTarget.checked||(u.preventDefault(),We())}function xl(u){o(J)===G.VERIFYING?u.currentTarget.setCustomValidity(o(Me).waitAlert):o(_).validationMessage&&u.currentTarget.setCustomValidity(o(_).validationMessage)}function El(){br(o(_).display),We()}function Sl(){yr()}function Cl(u){let p=u.target;o(_).display==="floating"&&p&&!(y!=null&&y.contains(p))&&!p.hasAttribute("data-backdrop")&&!p.closest("[data-popover]")&&o(J)!==G.VERIFIED&&!o(_).floatingPersist&&Jr()}function li(u){let p=u.target;o(vt)==="onfocus"&&o(J)===G.UNVERIFIED&&o(U)&&p instanceof Element&&(o(U).contains(p)||p.form===o(U))&&fn()}function si(){br(o(_).display),We()}function ci(u){let p=u.target;(p==null?void 0:p.getAttribute("data-code-challenge"))!=="true"&&o(vt)==="onsubmit"&&o(J)===G.UNVERIFIED&&(u.preventDefault(),u.stopPropagation(),S(ne,u.submitter,!0),Zr(),fn().then(k=>{k&&!o(de)&&Jt().then(()=>{ui(o(ne))})}))}function Al(u){u.persisted&&(br(o(_).display),We())}function Tl(){yr()}function $l(u){var p,k;try{let w=JSON.parse(u);w&&typeof w=="object"&&Fn({serverVerificationFields:(p=w==null?void 0:w.sentinel)==null?void 0:p.fields,serverVerificationTimeZone:(k=w==null?void 0:w.sentinel)==null?void 0:k.timeZone,verifyUrl:w.verifyurl,...w})}catch(w){ee("unable to configure from x-altcha-config header",w)}}function Rl(u=20){if(!o(ae))return;let p=o(_).floatingPlacement;if(!o(B)&&(S(B,(o(_).floatingAnchor instanceof HTMLElement?o(_).floatingAnchor:o(_).floatingAnchor?document.querySelector(o(_).floatingAnchor):oi(l)[0])||o(U),!0),!o(B))){ee("unable to find floating anchor element");return}let k=parseInt(o(_).floatingOffset,10)||12,w=o(B).getBoundingClientRect(),A=o(ae).getBoundingClientRect(),$=document.documentElement.clientHeight,W=document.documentElement.clientWidth,Se=!p||p==="auto"?w.bottom+A.height+k+u>$:p==="top",re=Math.max(u,Math.min(W-u-A.width,w.left+w.width/2-A.width/2));if(o(ae).style.setProperty("--altcha-floating-left",`${re}px`),o(ae).style.setProperty("--altcha-floating-top",Se?`${w.top-(A.height+k)}px`:`${w.bottom+k}px`),o(ae).setAttribute("data-floating-position",Se?"top":"bottom"),o(F)){let $e=o(F).getBoundingClientRect();o(F).style.left=w.left-re+w.width/2-$e.width/2+"px"}}async function Pl(u,p){let k=await Hn("onRequestServerVerification",u,p);if(k!==void 0)return k;if(ee("requesting server verification from",o(_).verifyUrl),!o(_).verifyUrl)throw new Error("Parameter verifyUrl must be set for server verification.");let w=await o(_).fetch(mr(o(_).verifyUrl,o(z)),{body:JSON.stringify({code:p,fields:o(_).serverVerificationFields?wl():void 0,payload:u,timeZone:o(_).serverVerificationTimeZone?_l():void 0}),credentials:o(_).credentials||void 0,headers:{"Content-Type":"application/json"},method:"POST"});await fi(w);let A=await w.json();return A&&typeof A=="object"&&"payload"in A&&A.payload&&O("serververification",A),A}function ui(u){var p;o(U)&&"requestSubmit"in o(U)?o(U).requestSubmit(u):(p=o(U))!=null&&p.reportValidity()&&(u?u.click():o(U).submit())}function Il(u,p={}){let{domain:k,name:w=o(_).name,maxAge:A,path:$,sameSite:W,secure:Se}=p,re=`${encodeURIComponent(w)}=${encodeURIComponent(u)}`;k&&(re+=`; Domain=${k}`),A!=null&&(re+=`; Max-Age=${A}`),$&&(re+=`; Path=${$}`),W&&(re+=`; SameSite=${W}`),Se&&(re+="; Secure"),document.cookie=re}function br(u){switch(u){case"bar":case"floating":case"overlay":Jr(),(!o(vt)||o(vt)==="off")&&(o(be).auto="onsubmit");break;case"standard":Zr()}o(dt)!==u&&S(dt,u,!0)}function Ol(u){o(Le)&&clearTimeout(o(Le));let p=()=>{o(J)!==G.UNVERIFIED?(S(se,!1),qe(G.EXPIRED)):We(),O("expired")},k=u*1e3-Date.now();k>=1?S(Le,setTimeout(p,k),!0):p()}async function fi(u){var k;if(u.status>=400){if((k=u.headers.get("content-type"))!=null&&k.includes("/json")){let w;try{w=await u.json()}catch(A){}if(w&&"error"in w)throw new Error(`Server responded with ${u.status} - ${w.error}`)}throw new Error(`Server responded with ${u.status}.`)}let p=u.headers.get("content-type");if(!p||!p.includes("/json"))throw new Error(`Server responded with invalid content-type. Expected application/json, received ${p}.`)}async function hi(u){var k;if(!o(ie)){qe(G.ERROR,"Cannot verify code challenge without PoW payload.");return}qe(G.VERIFYING);let p=null;if(o(_).verifyUrl)p=await Pl(o(ie),u);else if(o(_).verifyFunction)p=await o(_).verifyFunction(o(ie),u);else{qe(G.ERROR,"Parameter verifyUrl is required for code challenge verification.");return}p!=null&&p.payload&&(S(ie,p.payload,!0),ee("server payload",o(ie))),(p==null?void 0:p.verified)===!0?(ee("verified"),qe(G.VERIFIED),O("verified",{payload:o(ie)}),o(vt)==="onsubmit"&&Jt().then(()=>{ui(o(ne))})):qe(G.ERROR,(p==null?void 0:p.reason)||"Verification failed."),o(_).disableAutoFocus||(k=qr())==null||k.focus()}function Fn(u){Object.assign(o(be),{...Object.fromEntries(Object.entries(u).filter(([p,k])=>k!==void 0))})}function Ll(){return{...o(_)}}function Ml(){return o(J)}function Jr(){S(Q,!1)}function ee(...u){(o(_).debug||u.some(p=>p instanceof Error))&&console[u[0]instanceof Error?"error":"log"]("ALTCHA",`[name=${o(_).name}]`,...u)}function We(u=G.UNVERIFIED,p=null){S(se,!1),S(_e,p,!0),S(ie,null),o(q)&&o(q).abort(),o(Le)&&(clearTimeout(o(Le)),S(Le,null)),qe(u)}function qe(u,p=null){S(J,u,!0),S(_e,p,!0),O("statechange",{payload:o(ie),state:o(J)})}function Zr(){S(Q,!0),Jt().then(()=>{yr()})}function yr(){if(o(_).display==="floating")return Rl();S(x,o(x)+1)}async function fn(u={}){var $e,Dt;let{concurrency:p=Math.max(1,o(_).workers),controller:k=new AbortController,minDuration:w=o(_).minDuration}=u,A=performance.now(),$=null,W=null,Se=!1,re=await Hn("onVerify",u);if(re!==void 0)return re;We(G.VERIFYING),S(q,k,!0);try{if(!f)throw new Error("Secure context (HTTPS) required.");if(o(_).mockError)throw new Error("Mock error.");if(o(_).test)return ee("running test mode with null challenge"),await Te(Math.max(0,w-(performance.now()-A))),($e=o(q))!=null&&$e.signal.aborted?(We(),null):(S(ie,btoa(JSON.stringify({challenge:null,solution:null,test:!0})),!0),ee("verified"),qe(G.VERIFIED),O("verified",{payload:o(ie)}),{payload:o(ie)});if($=await pt(),!$)throw new Error("Failed to fetch challenge.");ee("challenge",$),"configuration"in $&&(ee("re-configuring from challenge",$.configuration),Fn($.configuration)),$.parameters.expiresAt&&Ol($.parameters.expiresAt),Se="_version"in $&&$._version===1;let nt=globalThis.$altcha.algorithms.get($.parameters.algorithm);if(!nt)throw new Error(`Unsupported algorithm ${$.parameters.algorithm}.`);if(W=await cl({challenge:$,concurrency:p,controller:k,createWorker:nt,counterMode:Se?"string":"uint32",onOutOfMemory:yi=>{if(ee("out of memory error received"),O("outofmemory"),o(_).retryOnOutOfMemoryError&&yi>1){let wi=Math.floor(yi/2);return ee(`retrying with ${wi} workers...`),wi}},timeout:o(_).timeout}),(Dt=o(q))!=null&&Dt.signal.aborted)return We(),null;if(!W)throw new Error("Failed to find solution.");ee("solution",W),await Te(Math.max(0,w-(performance.now()-A))),S(de,$.codeChallenge||o(_).codeChallenge||null,!0),Se?S(ie,btoa(JSON.stringify(Wr($,W))),!0):S(ie,btoa(JSON.stringify({challenge:{parameters:$.parameters,signature:$.signature},solution:W})),!0),o(de)?(ee("requesting code verification"),qe(G.CODE),O("codechallenge",{codeChallenge:o(de)})):o(_).verifyUrl?await hi():(ee("verified"),qe(G.VERIFIED),O("verified",{payload:o(ie)}))}catch(nt){return ee("verification failed",nt),qe(G.ERROR,String(nt)),null}finally{S(q,null)}return{challenge:$,payload:o(ie),solution:W}}var Nl={configure:Fn,getConfiguration:Ll,getState:Ml,hide:Jr,log:ee,reset:We,setState:qe,show:Zr,updateUI:yr,verify:fn},di=mu();pe("scroll",ga,Sl),pe("click",ga,Cl),pe("pageshow",rn,Al),pe("resize",rn,Tl);var vi=vn(di);{var Dl=u=>{var p=uu();j(u,p)};ve(vi,u=>{o(_).display==="overlay"&&o(Q)&&u(Dl)})}var gt=oe(vi,2),pi=ye(gt);{var Ul=u=>{var p=hu(),k=vn(p),w=oe(k,2);{var A=$=>{var W=fu();qo(W,()=>{var Se;return(Se=document.querySelector(o(_).overlayContent))==null?void 0:Se.innerHTML},!0),he(W),j($,W)};ve(w,$=>{o(_).overlayContent&&$(A)})}pe("click",k,El,!0),j(u,p)};ve(pi,u=>{o(_).display==="overlay"&&o(Q)&&u(Ul)})}var Xr=oe(pi,2),Qr=ye(Xr),ea=ye(Qr),gi=ye(ea);{let u=Re(()=>o(_).display==="standard"&&o(vt)!=="onsubmit"||o(J)===G.VERIFYING);kc(gi,()=>o(Mn),(p,k)=>{k(p,{get id(){return o(Ln)},get form(){return t.form},name:"",get required(){return o(u)},get loading(){return o(Nn)},get checked(){return o(se)},onchange:kl,oninvalid:xl})})}var ta=oe(gi,2),Hl=ye(ta);{var Fl=u=>{var p=wr();Ce(()=>wt(p,o(Me).verificationRequired)),j(u,p)},Vl=u=>{var p=wr();Ce(()=>wt(p,o(Me).verifying)),j(u,p)},jl=u=>{var p=wr();Ce(()=>wt(p,o(Me).verified)),j(u,p)},zl=u=>{var p=wr();Ce(()=>wt(p,o(Me).label)),j(u,p)};ve(Hl,u=>{o(J)===G.CODE&&o(de)?u(Fl):o(J)===G.VERIFYING?u(Vl,1):o(J)===G.VERIFIED?u(jl,2):u(zl,-1)})}he(ta),he(ea);var Bl=oe(ea,2);{var Kl=u=>{ni(u,{get strings(){return o(Me)}})};ve(Bl,u=>{o(Yt)&&u(Kl)})}he(Qr);var mi=oe(Qr,2);{var Yl=u=>{{let p=Re(()=>o(_).display==="bar"&&o(Yt));Oa(u,{get logo(){return o(p)},get strings(){return o(Me)}})}};ve(mi,u=>{o(gr)&&u(Yl)})}var bi=oe(mi,2);{var Gl=u=>{var p=du();Bt(p,k=>S(F,k),()=>o(F)),j(u,p)};ve(bi,u=>{o(_).display==="floating"&&u(Gl)})}var Wl=oe(bi,2);{var ql=u=>{var p=vu();ei(p),Ce(()=>{K(p,"form",t.form),K(p,"name",o(_).name),Mc(p,o(ie))}),j(u,p)};ve(Wl,u=>{o(_).setCookie||u(ql)})}he(Xr);var Jl=oe(Xr,2);{var Zl=u=>{La(u,{get anchor(){return o(ae)},onClickOutside:()=>{f&&We()},get placement(){return o(_).popoverPlacement},role:"alert",variant:"error",get dir(){return o(Un)},get updateUISignal(){return o(x)},children:(p,k)=>{var w=Li(),A=vn(w);{var $=re=>{var $e=pu();j(re,$e)},W=re=>{var $e=Fi(),Dt=gn($e,!0);Ce(()=>wt(Dt,o(Me).expired)),j(re,$e)},Se=re=>{var $e=Fi(),Dt=gn($e,!0);Ce(()=>{K($e,"title",o(_e)),wt(Dt,o(Me).error)}),j(re,$e)};ve(A,re=>{!o(_e)&&!f?re($):!o(_e)&&o(J)===G.EXPIRED?re(W,1):re(Se,-1)})}j(p,w)},$$slots:{default:!0}})},Xl=u=>{var p=Li(),k=vn(p);_c(k,()=>o(de),w=>{{let A=Re(()=>o(_).codeChallengeDisplay!=="standard");La(w,{get anchor(){return o(ae)},get backdrop(){return o(A)},get display(){return o(_).codeChallengeDisplay},onClose:()=>{We()},get placement(){return o(_).popoverPlacement},role:"dialog",get"aria-label"(){return o(Me).verificationRequired},get dir(){return o(Un)},get updateUISignal(){return o(x)},children:($,W)=>{var Se=gu(),re=vn(Se);sl(re,{get audioUrl(){return o(E)},get imageUrl(){return o(Y)},onCancel:()=>We(),onReload:()=>fn(),onSubmit:nt=>hi(nt),get codeChallenge(){return o(de)},get config(){return o(_)},get strings(){return o(Me)}});var $e=oe(re,2);{var Dt=nt=>{Oa(nt,{get logo(){return o(Yt)},get strings(){return o(Me)}})};ve($e,nt=>{o(gr)&&o(_).codeChallengeDisplay!=="standard"&&nt(Dt)})}j($,Se)},$$slots:{default:!0}})}}),j(u,p)};ve(Jl,u=>{o(_e)||o(J)===G.EXPIRED||!f?u(Zl):o(de)&&o(J)===G.CODE&&u(Xl,1)})}he(gt),Bt(gt,u=>S(ae,u),()=>o(ae)),Ce(u=>{K(gt,"data-state",o(J)),K(gt,"data-display",o(_).display||void 0),K(gt,"data-placement",u),K(gt,"data-visible",o(Q)||void 0),K(gt,"dir",o(Un)),K(ta,"for",o(Ln)),gt.dir=gt.dir},[()=>bl(o(_).display)]),j(e,di);var Ql=Nt(Nl);return i(),Ql}typeof window!="undefined"&&window.customElements&&!customElements.get("altcha-widget")&&customElements.define("altcha-widget",Kt(bu,{auto:{type:"String"},challenge:{type:"String"},configuration:{type:"String"},display:{type:"String"},form:{type:"String"},language:{type:"String"},name:{type:"String"},theme:{type:"String"},type:{type:"String"},workers:{type:"Number"}},[],["configure","getConfiguration","getState","hide","log","reset","setState","show","updateUI","verify"]));var ul=`(function() {
  "use strict";
  function assertAlgorithm(algorithm, allowed) {
    if (!allowed.includes(algorithm)) {
      throw new Error(
        \`Unsupported algorithm: \${String(algorithm)}. Expected one of: \${allowed.join(", ")}.\`
      );
    }
  }
  function bufferStartsWith(buffer, prefix) {
    if (prefix.length > buffer.length) {
      return false;
    }
    for (let i = 0; i < prefix.length; i++) {
      if (buffer[i] !== prefix[i]) {
        return false;
      }
    }
    return true;
  }
  function bufferToHex(buffer) {
    return Array.from(new Uint8Array(buffer)).map((b) => b.toString(16).padStart(2, "0")).join("");
  }
  function concatBuffers(a, b) {
    const out = new Uint8Array(a.length + b.length);
    out.set(a, 0);
    out.set(b, a.length);
    return out;
  }
  function hexToBuffer(hex) {
    if (hex.length % 2 !== 0) {
      throw new Error(\`Hex string must have an even length. Got: \${hex}\`);
    }
    if (!/^[0-9a-fA-F]*$/.test(hex)) {
      throw new Error("Hex string contains non-hex characters.");
    }
    const buffer = new Uint8Array(hex.length / 2);
    for (let i = 0; i < buffer.length; i++) {
      buffer[i] = parseInt(hex.substring(i * 2, i * 2 + 2), 16);
    }
    return buffer;
  }
  async function delay(ms) {
    await new Promise((resolve) => setTimeout(resolve, ms));
  }
  function timeDuration(start) {
    return Math.floor((performance.now() - start) * 10) / 10;
  }
  var HmacAlgorithm = /* @__PURE__ */ ((HmacAlgorithm2) => {
    HmacAlgorithm2["SHA_256"] = "SHA-256";
    HmacAlgorithm2["SHA_384"] = "SHA-384";
    HmacAlgorithm2["SHA_512"] = "SHA-512";
    return HmacAlgorithm2;
  })(HmacAlgorithm || {});
  Object.values(HmacAlgorithm);
  const MAX_COUNTER = {
    string: Number.MAX_SAFE_INTEGER,
    uint32: 4294967295
  };
  function isValidCounter(n, mode) {
    return Number.isInteger(n) && n >= 0 && n <= MAX_COUNTER[mode];
  }
  class PasswordBuffer {
    constructor(nonce, mode = "uint32") {
      this.nonce = nonce;
      this.mode = mode;
      this.buffer = new Uint8Array(this.nonce.length + this.COUNTER_BYTES);
      this.buffer.set(this.nonce, 0);
      this.dataView = new DataView(this.buffer.buffer);
    }
    nonce;
    mode;
    COUNTER_BYTES = 4;
    buffer;
    dataView;
    encoder = new TextEncoder();
    /**
     * Appends the counter to the nonce buffer.
     * In 'string' mode, encodes the counter as a UTF-8 string.
     * In 'uint32' mode, writes the counter as a big-endian 32-bit integer.
     * Throws a RangeError unless the counter is an integer the mode encodes exactly.
     */
    setCounter(n) {
      if (!isValidCounter(n, this.mode)) {
        throw new RangeError(
          \`counter must be an integer from 0 to \${MAX_COUNTER[this.mode]}. Got: \${n}\`
        );
      }
      if (this.mode === "string") {
        return concatBuffers(this.nonce, this.encoder.encode(n.toString()));
      }
      this.dataView.setUint32(this.nonce.length, n, false);
      return this.buffer;
    }
  }
  function assertKeyPrefix(keyPrefix, keyLength) {
    if (typeof keyPrefix !== "string" || !/^[0-9a-fA-F]+$/.test(keyPrefix)) {
      throw new Error("keyPrefix must be a non-empty hex string.");
    }
    if (keyPrefix.length > keyLength * 2) {
      throw new Error(
        \`keyPrefix (\${keyPrefix.length} hex characters) must not be longer than the key (keyLength: \${keyLength} bytes).\`
      );
    }
  }
  async function solveChallenge(options) {
    const {
      challenge,
      controller,
      counterMode = "uint32",
      counterStart = 0,
      counterStep = 1,
      deriveKey: deriveKey2,
      timeout = 9e4
    } = options;
    const { nonce, keyLength = 32, keyPrefix, salt } = challenge.parameters;
    assertKeyPrefix(keyPrefix, keyLength);
    const nonceBuf = hexToBuffer(nonce);
    const saltBuf = hexToBuffer(salt);
    const keyPrefixHex = keyPrefix.toLowerCase();
    const keyPrefixBuf = keyPrefix.length % 2 === 0 ? hexToBuffer(keyPrefix) : null;
    const password = new PasswordBuffer(nonceBuf, counterMode);
    const start = performance.now();
    let counter = counterStart;
    let iterations = 0;
    let derivedKeyHex = "";
    let lastYield = start;
    while (true) {
      if (controller?.signal.aborted || timeout && iterations % 10 === 0 && performance.now() - start > timeout) {
        return null;
      }
      const { derivedKey } = await deriveKey2(
        challenge.parameters,
        saltBuf,
        password.setCounter(counter)
      );
      if (iterations % 10 === 0 && performance.now() - lastYield > 200) {
        await delay(0);
        lastYield = performance.now();
      }
      if (keyPrefixBuf ? bufferStartsWith(derivedKey, keyPrefixBuf) : bufferToHex(derivedKey).startsWith(keyPrefixHex)) {
        derivedKeyHex = bufferToHex(derivedKey);
        break;
      }
      counter = counter + counterStep;
      iterations = iterations + 1;
    }
    return {
      counter,
      derivedKey: derivedKeyHex,
      time: timeDuration(start)
    };
  }
  function handler(options) {
    const { deriveKey: deriveKey2 } = options;
    let controller = void 0;
    self.onmessage = async (message) => {
      const { challenge, counterMode, counterStart, counterStep, timeout, type } = message.data;
      if (type === "abort") {
        controller?.abort();
      } else if (type === "work") {
        controller = new AbortController();
        let solution;
        try {
          solution = await solveChallenge({
            challenge,
            controller,
            counterStart,
            counterStep,
            deriveKey: deriveKey2,
            counterMode,
            timeout
          });
        } catch (err) {
          return self.postMessage({ error: err });
        }
        self.postMessage(solution);
      }
    };
  }
  async function deriveKey(parameters, salt, password) {
    const { algorithm, cost, keyLength = 32 } = parameters;
    assertAlgorithm(algorithm, ["PBKDF2/SHA-256", "PBKDF2/SHA-384", "PBKDF2/SHA-512"]);
    const passwordKey = await crypto.subtle.importKey(
      "raw",
      password,
      { name: "PBKDF2" },
      false,
      ["deriveBits"]
    );
    const derivedBits = await crypto.subtle.deriveBits(
      {
        name: "PBKDF2",
        salt,
        iterations: cost,
        hash: algorithm.slice("PBKDF2/".length)
      },
      passwordKey,
      keyLength * 8
    );
    return {
      parameters: {},
      derivedKey: new Uint8Array(derivedBits)
    };
  }
  handler({
    deriveKey
  });
})();
`,Vi=typeof self!="undefined"&&self.Blob&&new Blob(["(self.URL || self.webkitURL).revokeObjectURL(self.location.href);",ul],{type:"text/javascript;charset=utf-8"});function ri(e){let t;try{if(t=Vi&&(self.URL||self.webkitURL).createObjectURL(Vi),!t)throw"";let n=new Worker(t,{name:e==null?void 0:e.name});return n.addEventListener("error",()=>{(self.URL||self.webkitURL).revokeObjectURL(t)}),n}catch(n){return new Worker("data:text/javascript;charset=utf-8,"+encodeURIComponent(ul),{name:e==null?void 0:e.name})}}var fl=`(function() {
  "use strict";
  function assertAlgorithm(algorithm, allowed) {
    if (!allowed.includes(algorithm)) {
      throw new Error(
        \`Unsupported algorithm: \${String(algorithm)}. Expected one of: \${allowed.join(", ")}.\`
      );
    }
  }
  function bufferStartsWith(buffer, prefix) {
    if (prefix.length > buffer.length) {
      return false;
    }
    for (let i = 0; i < prefix.length; i++) {
      if (buffer[i] !== prefix[i]) {
        return false;
      }
    }
    return true;
  }
  function bufferToHex(buffer) {
    return Array.from(new Uint8Array(buffer)).map((b) => b.toString(16).padStart(2, "0")).join("");
  }
  function concatBuffers(a, b) {
    const out = new Uint8Array(a.length + b.length);
    out.set(a, 0);
    out.set(b, a.length);
    return out;
  }
  function hexToBuffer(hex) {
    if (hex.length % 2 !== 0) {
      throw new Error(\`Hex string must have an even length. Got: \${hex}\`);
    }
    if (!/^[0-9a-fA-F]*$/.test(hex)) {
      throw new Error("Hex string contains non-hex characters.");
    }
    const buffer = new Uint8Array(hex.length / 2);
    for (let i = 0; i < buffer.length; i++) {
      buffer[i] = parseInt(hex.substring(i * 2, i * 2 + 2), 16);
    }
    return buffer;
  }
  async function delay(ms) {
    await new Promise((resolve) => setTimeout(resolve, ms));
  }
  function timeDuration(start) {
    return Math.floor((performance.now() - start) * 10) / 10;
  }
  var HmacAlgorithm = /* @__PURE__ */ ((HmacAlgorithm2) => {
    HmacAlgorithm2["SHA_256"] = "SHA-256";
    HmacAlgorithm2["SHA_384"] = "SHA-384";
    HmacAlgorithm2["SHA_512"] = "SHA-512";
    return HmacAlgorithm2;
  })(HmacAlgorithm || {});
  Object.values(HmacAlgorithm);
  const MAX_COUNTER = {
    string: Number.MAX_SAFE_INTEGER,
    uint32: 4294967295
  };
  function isValidCounter(n, mode) {
    return Number.isInteger(n) && n >= 0 && n <= MAX_COUNTER[mode];
  }
  class PasswordBuffer {
    constructor(nonce, mode = "uint32") {
      this.nonce = nonce;
      this.mode = mode;
      this.buffer = new Uint8Array(this.nonce.length + this.COUNTER_BYTES);
      this.buffer.set(this.nonce, 0);
      this.dataView = new DataView(this.buffer.buffer);
    }
    nonce;
    mode;
    COUNTER_BYTES = 4;
    buffer;
    dataView;
    encoder = new TextEncoder();
    /**
     * Appends the counter to the nonce buffer.
     * In 'string' mode, encodes the counter as a UTF-8 string.
     * In 'uint32' mode, writes the counter as a big-endian 32-bit integer.
     * Throws a RangeError unless the counter is an integer the mode encodes exactly.
     */
    setCounter(n) {
      if (!isValidCounter(n, this.mode)) {
        throw new RangeError(
          \`counter must be an integer from 0 to \${MAX_COUNTER[this.mode]}. Got: \${n}\`
        );
      }
      if (this.mode === "string") {
        return concatBuffers(this.nonce, this.encoder.encode(n.toString()));
      }
      this.dataView.setUint32(this.nonce.length, n, false);
      return this.buffer;
    }
  }
  function assertKeyPrefix(keyPrefix, keyLength) {
    if (typeof keyPrefix !== "string" || !/^[0-9a-fA-F]+$/.test(keyPrefix)) {
      throw new Error("keyPrefix must be a non-empty hex string.");
    }
    if (keyPrefix.length > keyLength * 2) {
      throw new Error(
        \`keyPrefix (\${keyPrefix.length} hex characters) must not be longer than the key (keyLength: \${keyLength} bytes).\`
      );
    }
  }
  async function solveChallenge(options) {
    const {
      challenge,
      controller,
      counterMode = "uint32",
      counterStart = 0,
      counterStep = 1,
      deriveKey: deriveKey2,
      timeout = 9e4
    } = options;
    const { nonce, keyLength = 32, keyPrefix, salt } = challenge.parameters;
    assertKeyPrefix(keyPrefix, keyLength);
    const nonceBuf = hexToBuffer(nonce);
    const saltBuf = hexToBuffer(salt);
    const keyPrefixHex = keyPrefix.toLowerCase();
    const keyPrefixBuf = keyPrefix.length % 2 === 0 ? hexToBuffer(keyPrefix) : null;
    const password = new PasswordBuffer(nonceBuf, counterMode);
    const start = performance.now();
    let counter = counterStart;
    let iterations = 0;
    let derivedKeyHex = "";
    let lastYield = start;
    while (true) {
      if (controller?.signal.aborted || timeout && iterations % 10 === 0 && performance.now() - start > timeout) {
        return null;
      }
      const { derivedKey } = await deriveKey2(
        challenge.parameters,
        saltBuf,
        password.setCounter(counter)
      );
      if (iterations % 10 === 0 && performance.now() - lastYield > 200) {
        await delay(0);
        lastYield = performance.now();
      }
      if (keyPrefixBuf ? bufferStartsWith(derivedKey, keyPrefixBuf) : bufferToHex(derivedKey).startsWith(keyPrefixHex)) {
        derivedKeyHex = bufferToHex(derivedKey);
        break;
      }
      counter = counter + counterStep;
      iterations = iterations + 1;
    }
    return {
      counter,
      derivedKey: derivedKeyHex,
      time: timeDuration(start)
    };
  }
  function handler(options) {
    const { deriveKey: deriveKey2 } = options;
    let controller = void 0;
    self.onmessage = async (message) => {
      const { challenge, counterMode, counterStart, counterStep, timeout, type } = message.data;
      if (type === "abort") {
        controller?.abort();
      } else if (type === "work") {
        controller = new AbortController();
        let solution;
        try {
          solution = await solveChallenge({
            challenge,
            controller,
            counterStart,
            counterStep,
            deriveKey: deriveKey2,
            counterMode,
            timeout
          });
        } catch (err) {
          return self.postMessage({ error: err });
        }
        self.postMessage(solution);
      }
    };
  }
  async function deriveKey(parameters, salt, password) {
    const { algorithm, keyLength = 32 } = parameters;
    assertAlgorithm(algorithm, ["SHA-256", "SHA-384", "SHA-512"]);
    const iterations = Math.max(1, parameters.cost);
    let derivedKey = concatBuffers(salt, password);
    for (let i = 0; i < iterations; i++) {
      derivedKey = new Uint8Array(await crypto.subtle.digest(algorithm, derivedKey));
    }
    return {
      parameters: {},
      derivedKey: derivedKey.slice(0, keyLength)
    };
  }
  handler({
    deriveKey
  });
})();
`,ji=typeof self!="undefined"&&self.Blob&&new Blob(["(self.URL || self.webkitURL).revokeObjectURL(self.location.href);",fl],{type:"text/javascript;charset=utf-8"});function ai(e){let t;try{if(t=ji&&(self.URL||self.webkitURL).createObjectURL(ji),!t)throw"";let n=new Worker(t,{name:e==null?void 0:e.name});return n.addEventListener("error",()=>{(self.URL||self.webkitURL).revokeObjectURL(t)}),n}catch(n){return new Worker("data:text/javascript;charset=utf-8,"+encodeURIComponent(fl),{name:e==null?void 0:e.name})}}var yu=`:root {
  --altcha-border-color: var(--altcha-color-neutral);
  --altcha-border-width: 1px;
  --altcha-border-radius: 6px;
  --altcha-color-base: light-dark(oklch(100% 0.00011 271.152), oklch(20.904% 0.00002 271.152));
  --altcha-color-base-content: light-dark(
  	oklch(20.904% 0.00002 271.152),
  	oklch(100% 0.00011 271.152)
  );
  --altcha-color-error: oklch(51.284% 0.20527 28.678);
  --altcha-color-error-content: oklch(100% 0.00011 271.152);
  --altcha-color-neutral: light-dark(oklch(83.591% 0.0001 271.152), oklch(46.04% 0.00005 271.152));
  --altcha-color-neutral-content: light-dark(
  	oklch(46.76% 0.00005 271.152),
  	oklch(100% 0.00011 271.152)
  );
  --altcha-color-primary: oklch(40.279% 0.2449 268.131);
  --altcha-color-primary-content: oklch(100% 0.00011 271.152);
  --altcha-color-success: oklch(55.748% 0.18968 142.511);
  --altcha-color-success-content: oklch(100% 0.00011 271.152);
  --altcha-checkbox-border-color: light-dark(
  	oklch(66.494% 0.00233 15.434),
  	oklch(51.028% 0.00006 271.152)
  );
  --altcha-checkbox-border-radius: 5px;
  --altcha-checkbox-border-width: var(--altcha-border-width);
  --altcha-checkbox-outline: 2px solid var(--altcha-checkbox-outline-color);
  --altcha-checkbox-outline-color: -webkit-focus-ring-color;
  --altcha-checkbox-outline-offset: 2px;
  --altcha-checkbox-size: 22px;
  --altcha-checkbox-transition-duration: var(--altcha-transition-duration);
  --altcha-input-background-color: var(--altcha-color-base);
  --altcha-input-border-radius: 3px;
  --altcha-input-border-width: 1px;
  --altcha-input-color: var(--altcha-color-base-content);
  --altcha-max-width: 320px;
  --altcha-padding: 0.75rem;
  --altcha-popover-arrow-size: 6px;
  --altcha-popover-color: var(--altcha-border-color);
  --altcha-shadow: drop-shadow(3px 3px 6px oklch(0% 0 0 / 0.2));
  --altcha-spinner-color: var(--altcha-color-base-content);
  --altcha-switch-background-color: var(--altcha-color-neutral);
  --altcha-switch-border-radius: calc(infinity * 1px);
  --altcha-switch-height: var(--altcha-checkbox-size);
  --altcha-switch-padding: 0.25rem;
  --altcha-switch-width: calc(var(--altcha-checkbox-size) * 1.75);
  --altcha-switch-toggle-border-radius: 100%;
  --altcha-switch-toggle-color: var(--altcha-color-neutral-content);
  --altcha-switch-toggle-size: calc(
  	var(--altcha-switch-height) - calc(var(--altcha-switch-padding) * 2)
  );
  --altcha-transition-duration: 0.6s;
  --altcha-z-index: 99999999;
  --altcha-z-index-popover: 999999999;
}

@supports (-moz-appearance: none) {
  :root {
    --altcha-checkbox-outline-color: var(--altcha-color-primary);
  }
}
.altcha {
  all: revert-layer;
  display: none;
  font-family: inherit;
  font-size: inherit;
  position: relative;
}
.altcha[data-visible] {
  display: block;
}
.altcha-popover, .altcha-popover * {
  all: revert-layer;
  box-sizing: border-box;
  font-family: inherit;
  font-size: inherit;
  line-height: 1.25;
}
.altcha * {
  all: revert-layer;
  box-sizing: border-box;
  font-family: inherit;
  font-size: inherit;
  line-height: 1.25;
}
.altcha a, .altcha-popover a {
  color: currentColor;
  text-decoration: none;
}
.altcha a:hover, .altcha-popover a:hover {
  color: currentColor;
}
.altcha-main {
  align-items: start;
  background-color: var(--altcha-color-base);
  border: var(--altcha-border-width, 1px) solid var(--altcha-border-color);
  border-radius: var(--altcha-border-radius, 0);
  color: var(--altcha-color-base-content);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  justify-content: space-between;
  padding: var(--altcha-padding);
  max-width: var(--altcha-max-width, 100%);
}
.altcha-main > * {
  display: flex;
  width: 100%;
}
.altcha-main > *:first-child {
  flex-grow: 1;
}
.altcha-checkbox-wrap {
  align-items: center;
  display: flex;
  flex-direction: row;
  flex-grow: 1;
  gap: 0.5rem;
}
.altcha-checkbox-wrap > * {
  display: flex;
}
.altcha-logo {
  opacity: 0.7;
}
.altcha-footer {
  align-items: center;
  display: flex;
  flex-grow: 1;
  gap: 0.5rem;
  justify-content: flex-end;
  font-size: 0.7rem;
  opacity: 0.7;
}
.altcha-footer p {
  margin: 0;
  padding: 0;
}
.altcha-error {
  font-size: 0.85rem;
}
.altcha-button {
  align-items: center;
  background: var(--altcha-color-primary);
  border: var(--altcha-input-border-width) solid var(--altcha-color-primary);
  border-radius: var(--altcha-input-border-radius);
  color: var(--altcha-color-primary-content);
  cursor: pointer;
  display: flex;
  font-size: 0.9rem;
  gap: 0.5rem;
  padding: 0.35rem;
}
.altcha-button:focus {
  border-color: var(--altcha-color-primary);
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-button > .altcha-spinner, .altcha-button > svg {
  height: 20px;
  width: 20px;
}
.altcha-button-secondary {
  background: transparent;
  border-color: var(--altcha-color-neutral);
  color: var(--altcha-color-neutral-content);
}
.altcha-input {
  background: var(--altcha-input-background-color);
  border: var(--altcha-input-border-width) solid var(--altcha-color-neutral);
  border-radius: var(--altcha-input-border-radius);
  color: var(--altcha-input-color);
  flex-grow: 1;
  font-size: 1rem;
  min-width: 0;
  padding: 0.25rem;
  width: auto;
}
.altcha-input:focus {
  border-color: var(--altcha-color-primary);
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-spinner {
  animation: altcha-rotate 0.6s linear infinite;
  border-radius: 100%;
  border: var(--altcha-checkbox-border-width) solid var(--altcha-spinner-color);
  border-bottom-color: transparent;
  border-right-color: transparent;
  opacity: 0.7;
}
.altcha-popover {
  background-color: var(--altcha-color-base);
  border: var(--altcha-border-width) solid var(--altcha-border-color);
  border-radius: var(--altcha-border-radius);
  color: var(--altcha-color-base-content);
  filter: var(--altcha-shadow);
  position: absolute;
  left: calc(var(--altcha-padding) / 2);
  max-width: calc(var(--altcha-max-width) - var(--altcha-padding));
  top: calc(var(--altcha-padding) + var(--altcha-checkbox-size) + var(--altcha-popover-arrow-size));
  z-index: var(--altcha-z-index-popover);
}
.altcha-popover-arrow {
  border: var(--altcha-popover-arrow-size) solid transparent;
  border-bottom-color: var(--altcha-popover-color);
  content: "";
  height: 0;
  left: calc(var(--altcha-checkbox-size) / 2);
  position: absolute;
  top: calc(var(--altcha-popover-arrow-size) * -2);
  width: 0;
}
.altcha-popover-content {
  max-height: 100dvh;
  overflow: auto;
  padding: var(--altcha-padding);
}
.altcha-popover[data-top=true][data-display=standard] {
  bottom: calc(100% - (var(--altcha-padding) - var(--altcha-popover-arrow-size)));
  top: auto;
}
.altcha-popover[data-top=true][data-display=standard] .altcha-popover-arrow {
  border-bottom-color: transparent;
  border-top-color: var(--altcha-popover-color);
  bottom: calc(var(--altcha-popover-arrow-size) * -2);
  top: auto;
}
.altcha-popover[data-variant=error] {
  --altcha-popover-color: var(--altcha-color-error);
  background-color: var(--altcha-color-error);
  border-color: var(--altcha-color-error);
  color: var(--altcha-color-error-content);
}
.altcha-popover[data-variant=error] .altcha-popover-content {
  padding: calc(var(--altcha-padding) / 1.5) var(--altcha-padding);
}
.altcha-popover[data-display=overlay] {
  animation: altcha-overlay-slidein 0.5s forwards;
  left: 50%;
  position: fixed;
  top: 45%;
  transform: translate(-50%, -50%);
  width: var(--altcha-max-width);
  z-index: var(--altcha-z-index);
}
.altcha-popover[data-display=bottomsheet] {
  animation: altcha-bottomsheet-slideup 0.5s forwards;
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  border-bottom: 0;
  bottom: -100%;
  left: 50%;
  position: fixed;
  top: auto;
  transform: translate(-50%, 0);
  width: var(--altcha-max-width);
  z-index: var(--altcha-z-index);
}
.altcha-popover[data-display=bottomsheet] .altcha-popover-content {
  padding-bottom: calc(var(--altcha-padding) * 2);
}
.altcha-popover-backdrop {
  background: var(--altcha-color-base-content);
  bottom: 0;
  left: 0;
  opacity: 0.1;
  position: fixed;
  right: 0;
  top: 0;
  transition: opacity 0.5s;
  z-index: var(--altcha-z-index);
}
.altcha-popover-close {
  color: var(--altcha-color-base-content);
  cursor: pointer;
  display: inline-block;
  font-size: 1rem;
  height: 1.25rem;
  line-height: 0.95;
  position: absolute;
  right: 0;
  text-align: center;
  text-shadow: 0 0 1px var(--altcha-color-base);
  top: -1.5rem;
  width: 1.25rem;
  z-index: var(--altcha-z-index);
}
[dir=rtl] .altcha-popover {
  left: auto;
  right: calc(var(--altcha-padding) / 2);
}
[dir=rtl] .altcha-popover-arrow {
  left: auto;
  right: calc(var(--altcha-checkbox-size) / 2);
}
[dir=rtl] .altcha-popover-close {
  left: 0;
  right: auto;
}
.altcha-popover[data-display=bottomsheet] .altcha-footer, .altcha-popover[data-display=overlay] .altcha-footer {
  align-items: center;
  justify-content: center;
  padding-top: 1rem;
  gap: 0.5rem;
}
.altcha-popover[data-display=bottomsheet] .altcha-footer svg, .altcha-popover[data-display=overlay] .altcha-footer svg {
  height: 18px;
  width: 18px;
  vertical-align: middle;
}
.altcha-code-challenge > form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.altcha-code-challenge-title {
  font-weight: 600;
}
.altcha-code-challenge-text {
  font-size: 0.85rem;
}
.altcha-code-challenge-image {
  background: white;
  border: var(--altcha-input-border-width) solid var(--altcha-color-neutral);
  border-radius: var(--altcha-input-border-radius);
  object-fit: contain;
  height: 50px;
}
.altcha-code-challenge-row {
  display: flex;
  gap: 0.5rem;
}
.altcha-code-challenge-buttons {
  align-items: center;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: var(--altcha-padding);
  justify-content: space-between;
}
.altcha-code-challenge-buttons button {
  justify-content: center;
  width: 100%;
}
.altcha-checkbox {
  cursor: pointer;
  height: var(--altcha-checkbox-size);
  position: relative;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox input {
  appearance: none;
  background: var(--altcha-input-background-color);
  border: var(--altcha-checkbox-border-width, 2px) solid var(--altcha-checkbox-border-color);
  border-radius: var(--altcha-checkbox-border-radius);
  cursor: pointer;
  height: var(--altcha-checkbox-size);
  left: 0;
  margin: 0;
  padding: 0;
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
@supports (hanging-punctuation: first) and (font: -apple-system-body) and (-webkit-appearance: none) {
  .altcha-checkbox input {
    /* Safari-only: fixes focus outline */
  }
  .altcha-checkbox input:focus {
    outline-width: 2px;
    outline-style: solid;
  }
}
.altcha-checkbox input:before {
  border-radius: var(--altcha-checkbox-border-radius);
  content: "";
  width: 100%;
  height: 100%;
  background: var(--altcha-color-neutral);
  display: block;
  transform: scale(0);
}
.altcha-checkbox input:checked {
  background-color: var(--altcha-color-success);
  border-color: var(--altcha-color-success);
}
.altcha-checkbox input:checked::before {
  background-color: var(--altcha-color-success);
  opacity: 0;
  transform: scale(2.2);
  transition: all var(--altcha-checkbox-transition-duration) ease;
  transition-delay: 0.1s;
}
.altcha-checkbox svg {
  --altcha-radio-svg-size: calc(var(--altcha-checkbox-size) * 0.5);
  --altcha-radio-svg-offset: calc(var(--altcha-checkbox-size) * 0.25);
  fill: none;
  left: var(--altcha-radio-svg-offset);
  height: var(--altcha-radio-svg-size);
  opacity: 0;
  position: absolute;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 16px;
  stroke-dashoffset: 16px;
  top: var(--altcha-radio-svg-offset);
  transform: translate3d(0, 0, 0);
  width: var(--altcha-radio-svg-size);
}
.altcha-checkbox input:checked + svg {
  color: var(--altcha-color-success-content);
  opacity: 1;
  stroke-dashoffset: 0;
  transition: all var(--altcha-checkbox-transition-duration) ease;
  transition-delay: 0.1s;
}
.altcha-checkbox-spinner {
  display: none;
  left: 0;
  height: var(--altcha-checkbox-size);
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox[data-loading=true] input {
  appearance: none;
  opacity: 0;
  pointer-events: none;
}
.altcha-checkbox[data-loading=true] .altcha-checkbox-spinner {
  display: block;
}
.altcha-checkbox-native {
  height: var(--altcha-checkbox-size);
  position: relative;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native input {
  height: var(--altcha-checkbox-size);
  margin: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native-spinner {
  display: none;
  left: 0;
  height: var(--altcha-checkbox-size);
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native[data-loading=true] input {
  appearance: none;
  opacity: 0;
  pointer-events: none;
}
.altcha-checkbox-native[data-loading=true] .altcha-checkbox-native-spinner {
  display: block;
}
.altcha-switch {
  align-items: center;
  border-radius: var(--altcha-switch-border-radius);
  background-color: var(--altcha-switch-background-color);
  display: flex;
  height: var(--altcha-switch-height);
  padding: var(--altcha-switch-padding);
  position: relative;
  width: var(--altcha-switch-width);
}
.altcha-switch:focus-within {
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-switch input {
  appearance: none;
  cursor: pointer;
  height: 100%;
  left: 0;
  opacity: 0;
  position: absolute;
  top: 0;
  width: 100%;
}
.altcha-switch-toggle {
  align-items: center;
  background-color: var(--altcha-switch-toggle-color);
  border-radius: var(--altcha-switch-toggle-border-radius);
  cursor: pointer;
  display: flex;
  height: var(--altcha-switch-toggle-size);
  justify-content: center;
  left: var(--altcha-switch-padding);
  position: absolute;
  transition: width 150ms ease-out, left 150ms ease-out;
  width: var(--altcha-switch-toggle-size);
}
.altcha-switch-spinner {
  display: none;
  height: var(--altcha-switch-toggle-size);
  width: var(--altcha-switch-toggle-size);
}
.altcha-switch[data-loading=true] {
  pointer-events: none;
}
.altcha-switch[data-loading=true] .altcha-switch-spinner {
  display: block;
}
.altcha-switch[data-loading=true] .altcha-switch-toggle {
  background-color: transparent;
  left: calc(50% - var(--altcha-switch-toggle-size) / 2);
}
[data-state=verified] .altcha-switch {
  --altcha-switch-background-color: var(--altcha-color-success);
}
[data-state=verified] .altcha-switch-toggle {
  background-color: var(--altcha-color-success-content);
  left: calc(100% - var(--altcha-switch-height) + var(--altcha-switch-padding));
}
[dir=rtl] .altcha-switch-toggle {
  left: calc(100% - var(--altcha-switch-height) + var(--altcha-switch-padding));
}
[dir=rtl][data-state=verified] .altcha-switch-toggle {
  left: var(--altcha-switch-padding);
}
.altcha-floating-arrow {
  border: 6px solid transparent;
  border-bottom-color: var(--altcha-border-color);
  content: "";
  height: 0;
  left: 12px;
  position: absolute;
  top: -12px;
  width: 0;
}
.altcha-overlay-backdrop {
  bottom: 0;
  left: 0;
  position: fixed;
  right: 0;
  top: 0;
  transition: opacity var(--altcha-transition-duration);
  z-index: var(--altcha-z-index);
}
.altcha-overlay-close {
  display: inline-block;
  color: currentColor;
  cursor: pointer;
  font-size: 1rem;
  height: 1rem;
  line-height: 0.85;
  position: absolute;
  right: 0;
  text-align: center;
  text-shadow: 0 0 1px var(--altcha-color-base);
  top: -1.5rem;
  width: 1rem;
  z-index: var(--altcha-z-index);
}
.altcha[data-display=overlay] {
  animation: altcha-overlay-slidein var(--altcha-transition-duration) forwards;
  filter: var(--altcha-shadow);
  left: 50%;
  opacity: 0;
  position: fixed;
  top: 45%;
  transform: translate(-50%, -50%);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=overlay] .altcha-main {
  width: var(--altcha-max-width);
}
.altcha[data-display=floating] {
  display: none;
  filter: var(--altcha-shadow);
  left: var(--altcha-floating-left, -100%);
  position: fixed;
  top: var(--altcha-floating-top, -100%);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=floating] .altcha-main {
  width: var(--altcha-max-width);
}
.altcha[data-display=floating][data-floating-position=top] .altcha-floating-arrow {
  border-bottom-color: transparent;
  border-top-color: var(--altcha-border-color);
  bottom: -12px;
  top: auto;
}
.altcha[data-display=floating][data-visible] {
  display: flex;
}
.altcha[data-display=bar] {
  bottom: -100%;
  filter: var(--altcha-shadow);
  left: 0;
  position: fixed;
  right: 0;
  transition: bottom var(--altcha-transition-duration), top var(--altcha-transition-duration);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=bar] .altcha-main {
  align-items: center;
  border-radius: 0;
  border-width: var(--altcha-border-width) 0 0 0;
  flex-direction: row;
  max-width: 100% !important;
}
.altcha[data-display=bar] .altcha-main > * {
  width: auto;
}
.altcha[data-display=bar][data-placement=top] {
  bottom: auto;
  top: -100%;
}
.altcha[data-display=bar][data-placement=top] .altcha-main {
  border-width: 0 0 var(--altcha-border-width) 0;
}
.altcha[data-display=bar][data-placement=bottom]:not([data-state=unverified]) {
  bottom: 0;
}
.altcha[data-display=bar][data-placement=top]:not([data-state=unverified]) {
  top: 0;
}
.altcha[data-display=invisible] {
  display: none;
}

@keyframes altcha-rotate {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
@keyframes altcha-bottomsheet-slideup {
  100% {
    bottom: 0;
  }
}
@keyframes altcha-overlay-slidein {
  100% {
    opacity: 1;
    top: 50%;
  }
}`;cu(yu);$altcha.algorithms.set("SHA-256",()=>new ai);$altcha.algorithms.set("SHA-384",()=>new ai);$altcha.algorithms.set("SHA-512",()=>new ai);$altcha.algorithms.set("PBKDF2/SHA-256",()=>new ri);$altcha.algorithms.set("PBKDF2/SHA-384",()=>new ri);$altcha.algorithms.set("PBKDF2/SHA-512",()=>new ri);var wu="lcmt:captcha-ready",ii={id:"string",field:"string",token:"function",reset:"function"};function _u(e){var t={};return Object.keys(ii).forEach(function(n){var r=e?e[n]:void 0;if(typeof r!==ii[n]||r==="")throw new TypeError('Captcha adapter: "'+n+'" must be a '+(ii[n]==="string"?"non-empty string":"function")+".");t[n]=r}),Object.freeze(t)}function hl(e,t=window){var n=_u(e);return t.lcmtMailerCaptchas=t.lcmtMailerCaptchas||{},t.lcmtMailerCaptchas[n.id]=n,t.document.dispatchEvent(new CustomEvent(wu,{detail:{id:n.id}})),n}function dl(e,t=Date.now(),n=3e4){try{var r=JSON.parse(atob(e)),a=r&&r.challenge&&r.challenge.parameters&&r.challenge.parameters.expiresAt;return typeof a!="number"||a*1e3-t<n}catch(i){return!0}}(function(){"use strict";var e=3e4;function t(s){return new Promise(function(l,c){var f=setTimeout(function(){g(),c(new Error("ALTCHA timed out"))},e);function d(v){g(),l(v.detail&&v.detail.payload)}function m(v){v.detail&&v.detail.state==="error"&&(g(),c(new Error("ALTCHA failed")))}function g(){clearTimeout(f),s.removeEventListener("verified",d),s.removeEventListener("statechange",m)}s.addEventListener("verified",d),s.addEventListener("statechange",m),s.reset(),s.verify()})}function n(s){var l=s.closest("form"),c=l?l.querySelector('input[name="altcha"]'):null,f=c?c.value:"";return f&&!dl(f)?Promise.resolve(f):t(s)}function r(s){typeof s.reset=="function"&&s.reset()}function a(){var s=globalThis.$altcha&&globalThis.$altcha.i18n;if(s){var l={};s.store.subscribe(function(c){l=c})(),Object.keys(l).forEach(function(c){s.set(c,Object.assign({},l[c],{waitAlert:""}))})}}function i(){a();for(var s=document.querySelectorAll("altcha-widget"),l=0;l<s.length;l++){var c=s[l].getAttribute("challenge")||s[l].getAttribute("challengeurl");c&&/^(https?:)?\//.test(c)&&s[l].setAttribute("challenge",c+(c.indexOf("?")===-1?"?":"&")+"t="+Date.now())}setTimeout(function(){hl({id:"altcha",field:"altcha",token:n,reset:r})},0)}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",i):i()})();})();
