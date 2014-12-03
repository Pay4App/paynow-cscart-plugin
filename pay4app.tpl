<div class="control-group">
    <label class="control-label" for="pay4app_merchantid">Merchant ID:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][merchantid]" id="pay4app_merchantid" value="{$processor_params.merchantid}" class="input-text" size="60" />
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="pay4app_apisecret">Secret Key:</label>
    <div class="controls">
        <input type="password" name="payment_data[processor_params][apisecret]" id="pay4app_apisecret" value="{$processor_params.apisecret}" class="input-text" size="60" />
    </div>
</div>

{include file="common/subheader.tpl" title="Pay4App status map" target="#text_pay4app_status_map"}

<div id="text_pay4app_status_map" class="in collapse">
    {assign var="statuses" value=$smarty.const.STATUSES_ORDER|fn_get_simple_statuses}
       
    <div class="control-group">
        <label class="control-label" for="elm_paypal_completed">Completed:</label>
        <div class="controls">
            <select name="payment_data[processor_params][statuses][completed]" id="elm_pay4app_completed">
                {foreach from=$statuses item="s" key="k"}
                <option value="{$k}" {if (isset($processor_params.statuses.completed) && $processor_params.statuses.completed == $k) || (!isset($processor_params.statuses.completed) && $k == 'P')}selected="selected"{/if}>{$s}</option>
                {/foreach}
            </select>
        </div>
    </div>
    
    
</div>
