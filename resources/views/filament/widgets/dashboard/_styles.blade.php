{{-- Gemeinsame Styles der Dashboard-Bausteine (eigenes CSS, da Filament ohne eigenes Theme läuft) --}}
@once
<style>
    .psa-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
    .psa-tile { position: relative; display: block; border-radius: .9rem; padding: 1rem 1.1rem; text-decoration: none;
        background: linear-gradient(145deg, rgba(245, 158, 11, .07), rgba(56, 189, 248, .05));
        border: 1px solid rgba(148, 163, 184, .18); transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease; }
    .psa-tile:hover { transform: translateY(-2px); border-color: rgba(245, 158, 11, .55); box-shadow: 0 10px 25px -12px rgba(245, 158, 11, .45); }
    .psa-logo { height: 44px; display: flex; align-items: center; margin-bottom: .75rem; }
    .psa-logo img { height: 36px; width: auto; max-width: 160px; object-fit: contain; }
    :is(.dark) .psa-logo img { background: rgba(255, 255, 255, .92); padding: 4px 8px; border-radius: 6px; }
    .psa-logo-text { font-weight: 800; font-size: 1.05rem; letter-spacing: .04em; text-transform: uppercase; color: #f59e0b; }
    .psa-nums { display: flex; gap: 1.25rem; }
    .psa-num { font-size: 1.55rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .psa-cap { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; opacity: .65; }
    .psa-bar { height: 6px; border-radius: 99px; background: rgba(148, 163, 184, .2); overflow: hidden; margin-top: .8rem; }
    .psa-bar > span { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #f59e0b, #22c55e); }
    .psa-small { font-size: .75rem; opacity: .7; margin-top: .3rem; }
    .psa-more { margin-top: 1rem; font-size: .85rem; font-weight: 600; color: #f59e0b; cursor: pointer; background: none; border: 0; }

    .psa-rings { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); text-align: center; }
    .psa-ring { --p: 0; --c: #f59e0b; width: 120px; height: 120px; margin: 0 auto .6rem; border-radius: 50%; display: grid; place-items: center;
        background: conic-gradient(var(--c) calc(var(--p) * 1%), rgba(148, 163, 184, .18) 0); }
    .psa-ring::before { content: ""; grid-area: 1 / 1; width: 92px; height: 92px; border-radius: 50%; background: var(--psa-bg, #fff); }
    :is(.dark) .psa-ring::before { --psa-bg: rgb(24, 24, 27); }
    .psa-ring b { grid-area: 1 / 1; z-index: 1; font-size: 1.5rem; font-weight: 800; }

    .psa-sources { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
    .psa-source { display: flex; gap: .75rem; align-items: flex-start; padding: .7rem .9rem; border-radius: .7rem; border: 1px solid rgba(148, 163, 184, .18); }
    .psa-dot { flex: none; width: 11px; height: 11px; border-radius: 50%; margin-top: .35rem; }
    .psa-dot.ok { background: #22c55e; box-shadow: 0 0 0 4px rgba(34, 197, 94, .18); animation: psa-pulse 2.4s infinite; }
    .psa-dot.old { background: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, .18); }
    .psa-dot.manual { background: #eab308; box-shadow: 0 0 0 4px rgba(234, 179, 8, .18); }
    @keyframes psa-pulse { 0%, 100% { box-shadow: 0 0 0 4px rgba(34, 197, 94, .18); } 50% { box-shadow: 0 0 0 7px rgba(34, 197, 94, .05); } }

    .psa-gallery { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
    .psa-card { display: block; border-radius: .8rem; overflow: hidden; text-decoration: none; border: 1px solid rgba(148, 163, 184, .18); transition: transform .15s ease; }
    .psa-card:hover { transform: translateY(-2px); }
    .psa-card .img { aspect-ratio: 1; background: #fff; display: grid; place-items: center; }
    .psa-card .img img { width: 100%; height: 100%; object-fit: contain; padding: .5rem; }
    .psa-card .txt { padding: .5rem .65rem; font-size: .78rem; line-height: 1.25; }
    .psa-card .txt b { display: block; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #f59e0b; }

    .psa-lights { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .psa-stack { display: flex; height: 14px; border-radius: 99px; overflow: hidden; background: rgba(148, 163, 184, .2); margin: .6rem 0 .4rem; }
    .psa-legend { display: flex; flex-wrap: wrap; gap: .9rem; font-size: .8rem; }
    .psa-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: .35rem; }
</style>
@endonce
