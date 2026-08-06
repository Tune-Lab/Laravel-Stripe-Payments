<?php



namespace App\Http\Controllers\Api;

use App\Actions\Billing\CreateCheckoutSessionAction;
use App\Exceptions\Billing\PaymentGatewayException;
use App\Http\Requests\CreateCheckoutSessionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thin by design: validate, delegate, serialise. No business rules here.
 */
final readonly class CheckoutController
{
    public function __construct(private CreateCheckoutSessionAction $createCheckoutSession) {}

    public function __invoke(CreateCheckoutSessionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $session = $this->createCheckoutSession->handle(
                user: $user,
                amount: $request->money(),
                description: $request->description(),
            );
        } catch (PaymentGatewayException $e) {
            report($e);

            return new JsonResponse(
                ['message' => 'Payment provider is unavailable. Try again in a moment.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return new JsonResponse(
            ['data' => ['checkout_url' => $session->url, 'session_id' => $session->id]],
            Response::HTTP_CREATED,
        );
    }
}
