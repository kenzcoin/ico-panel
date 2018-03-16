<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\User;
use App\Token;
use App\Jobs\ConfirmCreateTokenTx;

class TokenController extends Controller
{

    public function __construct() {
        $this->middleware(['auth', 'admin']);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data['tokens'] = Token::all();
        return view('token.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data['artists'] = User::where('role_id', 3)->get();
        return view('token.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'artist' => 'required|string|exists:users,id',
            'token_name' => 'required|string|unique:tokens,name',
            'token_symbol' => 'required|string|unique:tokens,symbol'
        ]);

        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => env('TOKEN_API_URL'),
            // You can set any number of default request options.
            'timeout'  => 10.0
        ]);

        $artist = User::find($request->input('artist'));

        $tokenRequestParams = [
            "artist_address" => $artist->wallet[0]->address,
            "token_name" => $request->input('token_name'),
            "token_symbol" => $request->input('token_symbol')
        ];
        $response = $client->request('POST', 'ico/create', [
            'http_errors' => false,
            'json' => $tokenRequestParams,
            'headers' => [
                'Authorization' => 'API-KEY ' . env('TOKEN_API_KEY')
            ]
        ]);

        if ($response->getStatusCode() == 200) {
            $result = json_decode($response->getBody()->getContents());
            if ($result->success) {
                $token = Token::create([
                    'user_id' => $request->input('artist'),
                    'tx_hash' => $result->tx_hash,
                    'name' => $request->input('token_name'),
                    'symbol' => $request->input('token_symbol')
                ]);

                ConfirmCreateTokenTx::dispatch($token);
                //ConfirmCreateTokenTx::dispatch($token)->delay(now()->addMinutes(1));

                return response()->json([
                    'success' => true,
                    'tx_hash' => $result->tx_hash
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Token API returns fail'
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'cannot reach token API server'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  App\Token $token
     * @return \Illuminate\Http\Response
     */
    public function show(Token $token)
    {
        $data['token'] = $token;
        return view('token.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Create new ICO stage
     * @param  \Illuminate\Http\Request  $request
     * @param  Token  $token
     * @return \Illuminate\Http\Response
     */
    public function createStage(Request $request, Token $token) {
        $this->validate($request, [
            'price' => 'required|numeric',
            'supply' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);

        $startDate = new Carbon($request->input('start_date'));
        $endtDate = new Carbon($request->input('end_date'));
        $price = $request->input('price') * 100;
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => env('TOKEN_API_URL'),
            // You can set any number of default request options.
            'timeout'  => 10.0
        ]);
        $tokenRequestParams = [
            "artist_address" => $token->user->wallet[0]->address,
            "start_date" => $startDate->timestamp,
            "end_date" => $endtDate->timestamp,
            "price" => $price,
            "supply" => $request->input('supply')
        ];
        $response = $client->request('POST', 'ico/stage/create', [
            'http_errors' => false,
            'json' => $tokenRequestParams,
            'headers' => [
                'Authorization' => 'API-KEY ' . env('TOKEN_API_KEY')
            ]
        ]);

        if ($response->getStatusCode() == 200) {
            $result = json_decode($response->getBody()->getContents());
            if ($result->success) {

                $token->stages()->create([
                    'start_at' => $startDate,
                    'end_at' => $endtDate,
                    'supply' => $request->input('supply'),
                    'price' => $price,
                    'tx_hash' => $result->tx_hash
                ]);
            }
        }
        
        return redirect()->route('tokens.show', [$token]);
    }
}
