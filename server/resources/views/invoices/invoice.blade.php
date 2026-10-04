@php use App\Support\Money; @endphp
<!doctype html>
<html><head><meta charset="utf-8"><style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1c2033; }
  h1 { font-size: 20px; margin: 0; color: {{ $brand['royalBlue'] }}; }
  .gold { color: {{ $brand['gold'] }}; }
  .bar { height: 5px; background: {{ $brand['gold'] }}; margin: 8px 0 14px; }
  table { width: 100%; border-collapse: collapse; }
  .items th { background: {{ $brand['royalBlue'] }}; color: #fff; padding: 6px 4px; text-align: left; font-size: 9px; }
  .items td { padding: 6px 4px; border-bottom: 1px solid #e3e5ee; }
  .r { text-align: right; } .muted { color: #6b7088; }
  .totals td { padding: 3px 4px; } .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid {{ $brand['royalBlue'] }}; padding-top: 6px; }
  .box { border: 1px solid #e3e5ee; padding: 8px; }
</style></head><body>
<table><tr>
  <td><h1>{{ $brand['name'] }}</h1><div class="muted">{{ $company['legalName'] }}<br>{{ $company['address'] }}<br>GSTIN: {{ $company['gstin'] }}</div></td>
  <td class="r"><div style="font-size:16px;font-weight:bold">TAX INVOICE</div>
    <div>No: {{ $invoiceNo }}</div><div>Date: {{ ($order->confirmed_at ?? $order->created_at)->format('d M Y') }}</div>
    <div>Order: {{ $order->number }}</div></td>
</tr></table>
<div class="bar"></div>
<table><tr>
  <td style="width:50%;vertical-align:top"><div class="box"><b>Billed / Shipped to</b><br>
    {{ $order->shipping_address['name'] }}<br>{{ $order->shipping_address['contact'] }} · {{ $order->shipping_address['phone'] }}<br>
    {{ $order->shipping_address['line1'] }}@if($order->shipping_address['line2']), {{ $order->shipping_address['line2'] }}@endif<br>
    {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }} {{ $order->shipping_address['pincode'] }}<br>
    @if($order->shipping_address['gstin'])GSTIN: {{ $order->shipping_address['gstin'] }}@else<span class="muted">GSTIN: unregistered</span>@endif</div></td>
  <td style="width:50%;vertical-align:top;padding-left:8px"><div class="box"><b>Supply details</b><br>
    Dispatched from: {{ $order->hub->name }}<br>Place of supply: {{ $order->shipping_address['state'] }} ({{ $order->shipping_address['state_code'] }})<br>
    Payment: {{ strtoupper($order->payment_method) }} · {{ ucfirst($order->payment_status) }}</div></td>
</tr></table>
<br>
<table class="items">
  <tr><th>#</th><th>Item</th><th>HSN</th><th class="r">Cases</th><th class="r">Rate/case</th><th class="r">Discount</th><th class="r">Taxable</th><th class="r">GST%</th><th class="r">Amount</th></tr>
  @foreach($order->items as $i)
  <tr>
    <td>{{ $loop->iteration }}</td>
    <td>{{ $i->name }} <span class="muted">({{ $i->label }})</span>@if($i->free_quantity)<br><span class="gold">+ {{ $i->free_quantity }} free case(s)</span>@endif</td>
    <td>{{ $i->hsn_code }}</td><td class="r">{{ $i->quantity }}</td>
    <td class="r">{{ Money::fmt($i->unit_price, true) }}</td>
    <td class="r">{{ Money::fmt($i->deal_discount + $i->slab_discount + $i->coupon_discount, true) }}</td>
    <td class="r">{{ Money::fmt($i->taxable, true) }}</td><td class="r">{{ rtrim(rtrim($i->gst_rate, '0'), '.') }}</td>
    <td class="r">{{ Money::fmt($i->total, true) }}</td>
  </tr>
  @endforeach
</table>
<br>
<table><tr><td style="width:55%"></td><td>
<table class="totals">
  <tr><td>Gross value</td><td class="r">{{ Money::fmt($order->gross_amount, true) }}</td></tr>
  @if($order->deal_discount > 0)<tr><td>Deals</td><td class="r">- {{ Money::fmt($order->deal_discount, true) }}</td></tr>@endif
  @if($order->slab_discount > 0)<tr><td>Volume discount</td><td class="r">- {{ Money::fmt($order->slab_discount, true) }}</td></tr>@endif
  @if($order->coupon_discount > 0)<tr><td>Coupon {{ $order->coupon_code }}</td><td class="r">- {{ Money::fmt($order->coupon_discount, true) }}</td></tr>@endif
  <tr><td>Taxable value</td><td class="r">{{ Money::fmt($order->taxable_amount, true) }}</td></tr>
  @if($order->cgst > 0)<tr><td>CGST</td><td class="r">{{ Money::fmt($order->cgst, true) }}</td></tr><tr><td>SGST</td><td class="r">{{ Money::fmt($order->sgst, true) }}</td></tr>@endif
  @if($order->igst > 0)<tr><td>IGST</td><td class="r">{{ Money::fmt($order->igst, true) }}</td></tr>@endif
  <tr><td>Round off</td><td class="r">{{ Money::fmt($order->round_off, true) }}</td></tr>
  <tr class="grand"><td>Total</td><td class="r">{{ Money::fmt($order->total, true) }}</td></tr>
</table></td></tr></table>
<p class="muted" style="margin-top:30px">This is a computer-generated invoice. Goods once sold will be taken back only for damage or wrong supply reported within 48 hours of delivery.</p>
</body></html>
