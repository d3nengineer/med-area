<?php

declare(strict_types=1);

namespace Infrastructure\Repositories;

use Domain\AI\Recognise\DTO\RecogniseRequestDTO;
use Domain\AI\Recognise\Models\RecogniseRequest;
use Domain\AI\Recognise\Repositories\RecogniseRequestRepositoryContract;
use Shared\Exceptions\ServerErrorException;
use Shared\Repositories\BaseRepository;

class RecogniseRequestRepository extends BaseRepository implements RecogniseRequestRepositoryContract
{
    /**
     * @var class-string<RecogniseRequest>
     */
    protected string $model = RecogniseRequest::class;

    public function updateById(int $id, RecogniseRequestDTO $data): RecogniseRequestDTO
    {
        try {
            $model = $this->model::query()->findOrFail($id);

            $model->updateOrFail($data->toArray());

            return RecogniseRequestDTO::from([$model->refresh()]);
        } catch (\Throwable $e) {
            logger()->error('[RecogniseRequestRepository.updateById] DB operation failed', [
                'error'   => $e->getMessage(),
                'context' => ['id' => $id],
            ]);
            throw new ServerErrorException($e->getMessage());
        }
    }
}
