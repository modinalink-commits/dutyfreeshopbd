<script>(function(){var M={num:'Only numbers are allowed.',st:'Mobile number must start with 01.',ln:'Please enter a valid 11-digit mobile number.'};
function msg(v){if(!v)return '';if(!/^[0-9]+$/.test(v))return M.num;if(v.indexOf('01')!==0)return M.st;if(v.length!==11)return M.ln;return ''}
function box(el){var n=el.nextElementSibling;if(!n||!n.classList||!n.classList.contains('bdph-e')){n=document.createElement('div');n.className='bdph-e';n.style.cssText='color:#B91C1C;font-size:11.5px;margin-top:3px';el.parentNode.insertBefore(n,el.nextSibling)}return n}
function show(el,t){box(el).textContent=t||'';el.setCustomValidity(t||'')}
function prep(el){if(el._bd)return;el._bd=1;el.setAttribute('inputmode','numeric');el.setAttribute('maxlength','11');el.setAttribute('autocomplete','tel-national');el.setAttribute('pattern','01[0-9]{9}')}
document.addEventListener('focusin',function(e){var t=e.target;if(t&&t.matches&&t.matches('input[data-bdphone]'))prep(t)});
document.addEventListener('input',function(e){var t=e.target;if(!t||!t.matches||!t.matches('input[data-bdphone]'))return;prep(t);var raw=t.value.replace(/\s+/g,''),d=raw.replace(/[^0-9]/g,'').slice(0,11),bad=raw!==raw.replace(/[^0-9]/g,'');
 if(t.value!==d)t.value=d;show(t,bad?M.num:(d.length>=2?msg(d)==M.ln&&d.length<11?'':msg(d):''))});
document.addEventListener('focusout',function(e){var t=e.target;if(t&&t.matches&&t.matches('input[data-bdphone]')){t.value=t.value.trim();show(t,t.value?msg(t.value):'')}});
document.addEventListener('submit',function(e){var f=e.target,els=f.querySelectorAll?f.querySelectorAll('input[data-bdphone]'):[];for(var i=0;i<els.length;i++){var el=els[i];if(el.offsetParent===null)continue;var m=msg(el.value.trim());if(m||(!el.value&&el.required)){show(el,m||M.ln);e.preventDefault();e.stopImmediatePropagation();el.focus();return}}},true);
document.querySelectorAll('input[data-bdphone]').forEach(prep)})()</script>
