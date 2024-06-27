<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function validate(
        Request $request,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $validator = $this->getValidationFactory()
            ->make(
                $request->all(),
                $rules,
                $messages,
                $customAttributes
            );
        if ($validator->fails()) {
            $errors = (new \Illuminate\Validation\ValidationException($validator))->errors();
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(
                [
                    'success' => false,
                    'message' => $errors,
                    'data' => null
                ],
                \Illuminate\Http\JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            ));
        }
    }

    # return resource api
    public function apiResponse($error = 0, $msg = '', $result = null)
    {
        return response()->json(array(
            'error'         => $error,
            'mes'           => empty($msg) ? '' : $msg,
            'result'        => empty($result) ? [] : $result,
        ));
    }
}
