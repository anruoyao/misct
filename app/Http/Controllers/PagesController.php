<?php

namespace App\Http\Controllers;

use App\Models\Pages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagesController extends Controller
{

    function privacyPolicy()
    {
        $data = Pages::first();
        return $data->privacy;
    }

    function termsOfUse()
    {
        $data = Pages::first();
        return $data->termsofuse;
    }

    function viewTerms()
    {
        $data = Pages::first();
        return view('pages.viewTerms', ['data' => $data->termsofuse]);
    }

    function updatePrivacy(Request $request)
    {
        $data = Pages::first();
        $data->privacy = $request->content;
        $data->save();

        return  json_encode(['status' => true, 'message' => "更新成功"]);
    }

    function updateTerms(Request $request)
    {
        $data = Pages::first();
        $data->termsofuse = $request->content;
        $data->save();

        return  json_encode(['status' => true, 'message' => "更新成功"]);
    }

    function viewPrivacy()
    {
        
        $data = Pages::first();
        return view('pages.viewPrivacy', ['data' => $data->privacy]);
    }

    public function addContentForm(Request $request)
    {
        $privacy = Pages::first();
        if ($privacy) {
            if ($request->has('privacy')) {
                $privacy->privacy = $request->privacy;
            }
        }
        $privacy->save();

        return response()->json([
            'status' => true,
            'message' => '隐私政策已添加',
            'data' => $privacy,
        ]); 
    }

    public function addTermsForm(Request $request)
    {
        $terms = Pages::first();
        if ($terms) {
            if ($request->has('termsofuse')) {
                $terms->termsofuse = $request->termsofuse;
            }
        }
        $terms->save();

        return response()->json([
            'status' => true,
            'message' => '使用条款已添加',
            'data' => $terms,
        ]); 
    }

}
