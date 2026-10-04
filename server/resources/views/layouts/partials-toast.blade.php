<div class="snackbar-host" x-data="{ items: [], add(e) { const d = e.detail; const m = Array.isArray(d) ? d[0] : (d.message ? d : d[0] ?? d); const i = { id: Date.now() + Math.random(), msg: m.message ?? m, kind: m.kind ?? 'info' }; this.items.push(i); setTimeout(() => this.items = this.items.filter(x => x.id !== i.id), 4200); } }" @toast.window="add($event)">
  <template x-for="i in items" :key="i.id">
    <div class="snackbar" x-transition><span class="material-symbols-outlined" x-text="i.kind === 'error' ? 'error' : (i.kind === 'success' ? 'check_circle' : 'info')"></span><span x-text="i.msg"></span></div>
  </template>
</div>
@if(session('status'))<script>document.addEventListener('DOMContentLoaded',()=>window.dispatchEvent(new CustomEvent('toast',{detail:{message:@js(session('status')),kind:'success'}})));</script>@endif
