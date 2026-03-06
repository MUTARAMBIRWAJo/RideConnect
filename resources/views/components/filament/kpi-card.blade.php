@props([
    'icon' => '📈',
    'value' => null,
    'label' => '',
    'change' => null,
    'trend' => [],
    'color' => 'bg-green-700',
])

<div {{ $attributes->merge(['class' => 'rounded-2xl shadow-md hover:shadow-lg transform hover:scale-[1.02] transition p-6 bg-white dark:bg-gray-800']) }}>
  <div class="flex justify-between items-start">
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white" style="background: linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center {{ $color }}">{!! $icon !!}</div>
      </div>
      <div>
        <div class="text-sm text-gray-500 dark:text-gray-300">{{ $label }}</div>
        <div class="text-3xl font-semibold text-gray-900 dark:text-white">
          <span class="kpi-count">{{ $value ?? '—' }}</span>
        </div>
      </div>
    </div>
    @if($change !== null)
      <div>
        @php $isPos = intval($change) >= 0; @endphp
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $isPos ? 'text-green-800 bg-green-100' : 'text-red-700 bg-red-100' }}">
          {{ $isPos ? '+' : '' }}{{ $change }}%
        </span>
      </div>
    @endif
  </div>

  @if(!empty($trend))
    <div class="mt-4">
      <canvas class="kpi-sparkline" height="40" data-values='@json(array_values($trend))'></canvas>
    </div>
  @endif

  <script>
    (function(){
      const el = document.currentScript?.previousElementSibling?.querySelectorAll?.('.kpi-sparkline');
      if(!el) return;
      // lazy-load Chart.js if needed
      if(typeof Chart === 'undefined'){
        const s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/chart.js';document.head.appendChild(s);
        s.onload = ()=> render(el);
      } else render(el);
      function render(nodes){ nodes.forEach(n=>{
        try{ const values = JSON.parse(n.getAttribute('data-values')||'[]'); new Chart(n.getContext('2d'),{type:'line',data:{labels:values.map((_,i)=>i+1),datasets:[{data:values,borderWidth:1,borderColor:'rgba(22,101,52,0.9)',backgroundColor:'rgba(22,101,52,0.08)',tension:0.3}]},options:{responsive:true,plugins:{legend:{display:false}},elements:{point:{radius:0}},scales:{x:{display:false},y:{display:false}}}}) }catch(e){}
      }) }
    })();
  </script>
</div>
