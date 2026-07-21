<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title')</title>
<style>
:root{--bg:#07111f;--panel:#0d1d33;--panel2:#102541;--line:#29496f;--text:#f6f9ff;--muted:#a9bad1;--blue:#347ff0;--warn:#f3bd55}
*{box-sizing:border-box}html,body{min-height:100%}body{margin:0;display:grid;place-items:center;padding:24px;color:var(--text);font-family:Tahoma,Arial,"Segoe UI",sans-serif;background:radial-gradient(circle at 85% 10%,#183b68 0,transparent 34%),linear-gradient(145deg,var(--bg),#09182b 55%,#06101c)}
.shell{width:min(940px,100%)}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:14px;padding:13px 16px;border:1px solid #203d61;border-radius:16px;background:#081525cc}
.brand{display:flex;align-items:center;gap:12px}.mark{width:43px;height:43px;display:grid;place-items:center;border:1px solid #3d6697;border-radius:13px;background:linear-gradient(145deg,#1c4777,#0c1d32);font-size:22px}.brand b{display:block;font-size:16px}.brand small{display:block;margin-top:4px;color:var(--muted)}
.chip{padding:8px 11px;border:1px solid #82652f;border-radius:999px;color:#ffda8d;background:#513c171f;font-size:12px;font-weight:800}
.card{overflow:hidden;border:1px solid var(--line);border-radius:24px;background:linear-gradient(145deg,#112844fa,#09182bfc);box-shadow:0 28px 80px #0007}.grid{display:grid;grid-template-columns:minmax(0,1fr) 250px;gap:28px;align-items:center;min-height:470px;padding:50px}
.eyebrow{color:#a9caff;font-size:13px;font-weight:800}.title{margin:16px 0 0;font-size:clamp(31px,4vw,48px);line-height:1.25}.msg{margin:18px 0 0;color:var(--muted);font-size:17px;line-height:1.9}
.note{margin-top:24px;padding:14px 16px;border:1px solid #2a4668;border-radius:14px;background:#07142399;color:#c8d6e8;font-size:13px;line-height:1.7}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px}.btn{min-height:46px;padding:11px 18px;border-radius:13px;border:1px solid transparent;text-decoration:none;font:inherit;font-weight:900;cursor:pointer}.primary{color:#fff;background:linear-gradient(135deg,var(--blue),#245fc8)}.secondary{color:#e3ecf8;border-color:#46698e;background:#10243d}
.retry{margin-top:17px;color:#8095af;font-size:12px}.codebox{min-height:260px;display:grid;place-items:center;text-align:center;border:1px solid #365678;border-radius:22px;background:linear-gradient(155deg,#347ff025,#07142399)}.code{margin:0;font-size:clamp(76px,10vw,132px);line-height:.9;font-weight:900;letter-spacing:-.05em}.label{margin-top:13px;color:var(--muted);font-size:13px}
.foot{display:flex;justify-content:space-between;gap:16px;margin-top:13px;padding:0 5px;color:#70859f;font-size:11px}
@media(max-width:760px){.grid{grid-template-columns:1fr;padding:30px 22px}.codebox{order:-1;min-height:175px}.foot{flex-direction:column;text-align:center;align-items:center}}@media(max-width:470px){.chip,.brand small{display:none}.actions{display:grid}.btn{width:100%}}
</style>
</head>
<body>
<main class="shell">
<div class="top">
  <div class="brand"><div class="mark">📄</div><div><b>نظام الأرشفة الإلكترونية</b><small>قسم الشحن والتأمين</small></div></div>
  <div class="chip">@yield('status')</div>
</div>
<section class="card">
<div class="grid">
<div>
  <div class="eyebrow">تنبيه تقني</div>
  <h1 class="title">@yield('heading')</h1>
  <p class="msg">@yield('message')</p>
  <div class="note">@yield('note')</div>
  <div class="actions">
    <button class="btn primary" type="button" onclick="location.reload()">↻ إعادة المحاولة</button>
    <a class="btn secondary" href="/login">العودة إلى تسجيل الدخول</a>
  </div>
  <div class="retry">ستتم إعادة المحاولة تلقائيًا خلال <strong id="count">20</strong> ثانية.</div>
</div>
<div class="codebox"><div><p class="code">@yield('code')</p><div class="label">@yield('label')</div></div></div>
</div>
</section>
<div class="foot"><span>لم يتم فقدان أي مستند بسبب ظهور هذه الصفحة.</span><span>رمز الحالة: @yield('code')</span></div>
</main>
<script>
(function(){var s=20,e=document.getElementById('count');setInterval(function(){s--;if(e)e.textContent=Math.max(s,0);if(s<=0)location.reload()},1000)})();
</script>
</body>
</html>
