@php $zones = \App\Models\DeliveryZone::query()->where('is_active', true)->with('country')->orderBy('position')->get(); @endphp
<x-pages.document :title="__('Delivery information')" :eyebrow="__('Customer care')" :draft="false">
    <p>Products are printed to order by our partners in Rwanda, then delivered by courier. Delivery estimates start once your item has been produced.</p>
    <h2>Delivery zones and fees</h2>
    <div class="overflow-hidden rounded-xl border border-line bg-white">
        <table class="table-base">
            <thead><tr><th>{{ __('Zone') }}</th><th>{{ __('Fee') }}</th><th>{{ __('Free over') }}</th><th>{{ __('Estimate') }}</th></tr></thead>
            <tbody>
                @foreach ($zones as $zone)
                    <tr><td>{{ $zone->name }} ({{ $zone->country->name }})</td><td><x-ui.money :amount="$zone->fee_amount" :currency="$zone->country->currency_code" /></td><td>{!! $zone->free_over_amount ? e(\App\Support\Money::format($zone->free_over_amount, $zone->country->currency_code)) : '—' !!}</td><td>{{ $zone->estimateLabel() }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <h2>Production times</h2>
    <p>Apparel is usually ready in 3–4 business days, mugs, totes and stickers in 2 business days. You will receive an e-mail when your order ships.</p>
    <h2 id="sizes">Size guide (chest width, cm)</h2>
    <div class="overflow-hidden rounded-xl border border-line bg-white">
        <table class="table-base">
            <thead><tr><th>{{ __('Size') }}</th><th>S</th><th>M</th><th>L</th><th>XL</th><th>XXL</th></tr></thead>
            <tbody>
                <tr><td>{{ __('Classic T-shirt') }}</td><td>46</td><td>51</td><td>56</td><td>61</td><td>66</td></tr>
                <tr><td>{{ __('Oversized T-shirt') }}</td><td>53</td><td>58</td><td>63</td><td>68</td><td>73</td></tr>
                <tr><td>{{ __('Hoodie / sweatshirt') }}</td><td>51</td><td>56</td><td>61</td><td>66</td><td>71</td></tr>
            </tbody>
        </table>
    </div>
</x-pages.document>
