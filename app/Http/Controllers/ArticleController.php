<?php

namespace App\Http\Controllers;

use App\Admin;
use App\Models\Article;
use Illuminate\Http\Request;
use App\Http\Requests\ArticleRequest;
use App\Http\Controllers\BaseController;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use Table;

class ArticleController extends BaseController
{
    /**
     * @var string
     */
    private $route;

    /**
     * @var \App\Models\Article
     */
    private $model;

    /**
     * @var \App\Repositories\Interfaces\ArticleRepositoryInterface
     */
    private $repository;

    /**
     * ArticleController constructor.
     *
     * @param \App\Models\Article                                     $model
     * @param \App\Repositories\Interfaces\ArticleRepositoryInterface $repository
     */
    public function __construct(Article $model, ArticleRepositoryInterface $repository)
    {
        parent::__construct();

        $this->view .= 'article.';
        $this->route = '/article';
        $this->model = $model;
        $this->repository = $repository;

        view()->share('__title_page', 'Articles Management');
        view()->share('__route', $this->route);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function getData(Request $request)
    {
        $model = $this->repository->all('field',
            ['id', 'title', 'slug', 'status', 'created_by', 'created_at', 'updated_at']);

        if ( ! empty($request->filter_status)) {
            $model = $model->whereStatus($request->filter_status);
        }

        if ( ! empty($request->filter_author)) {
            $model = $model->whereCreatedBy($request->filter_author);
        }

        if ( ! empty($request->filter_start_date) && empty($request->filter_end_date)) {
            $model = $model->whereDate('created_at', '>=', $request->filter_start_date);
        }

        if (empty($request->filter_start_date) && ! empty($request->filter_end_date)) {
            $model = $model->whereDate('created_at', '<=', $request->filter_end_date);
        }

        if ( ! empty($request->filter_start_date) && ! empty($request->filter_end_date)) {
            $model = $model->whereDate('created_at', '>=', $request->filter_start_date)->whereDate('created_at', '<=',
                    $request->filter_end_date);
        }

        return Table::of($model)->addColumn('author', function ($model) {
                return $model->admin->name;
            })->addColumn('created_at', function ($model) {
                return reformatDateToReadable($model->created_at);
            })->addColumn('last_updated', function ($model) {
                return reformatDateToReadable($model->updated_at);
            })->addColumn('action', function ($model) {
                $status = $model->status == "publish" ? \Html::link(url($this->route . '/draft',
                    ['id' => encryptStringValue($model->id)]), 'Make as Draft',
                    ['class' => 'btn btn-warning btn-sm']) : \Html::link(url($this->route . '/publish',
                    ['id' => encryptStringValue($model->id)]), 'Publish Article',
                    ['class' => 'btn btn-primary btn-sm']);

                $preview = \Html::link(url($this->route . '/preview', ['slug' => $model->slug]), 'Preview Content',
                    ['class' => 'btn btn-secondary btn-sm', 'target' => '_blank']);

                $edit = \Html::link(url($this->route . '/edit', ['id' => encryptStringValue($model->id)]), 'Edit',
                    ['class' => 'btn btn-info btn-sm']);

                $delete = \Html::link(url($this->route . '/delete', ['id' => encryptStringValue($model->id)]), 'Delete',
                    ['class' => 'btn btn-danger btn-sm', 'onclick' => 'return confirm("Are you sure ?")']);

                return $status . " " . $preview . " " . $edit . " " . $delete;
            })->rawColumns(['action'])->make();
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getIndex()
    {
        $status = [
            ''        => 'Choose Status',
            'draft'   => 'Draft',
            'publish' => 'Publish'
        ];

        $author = $this->authorArray();

        return $this->renderView('index', [
            'status' => $status,
            'author' => \Arr::prepend($author, 'Choose Author', '')
        ]);
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getCreate()
    {
        return $this->renderView('form', [
            'action' => 'create',
            'model'  => $this->model,
        ]);
    }

    /**
     * @param \App\Http\Requests\ArticleRequest $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postCreate(ArticleRequest $request)
    {
        $result = $this->repository->create($request);

        alert('Success', 'New Article has been added.', 'success');

        return redirect(url($this->route));
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function getDraft($id)
    {
        $result = $this->repository->draftOrPublish(decryptStringValue($id), 'draft');

        alert('Success', 'Article has been drafted.', 'success');

        return redirect(url($this->route));
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function getPublish($id)
    {
        $result = $this->repository->draftOrPublish(decryptStringValue($id), 'publish');

        alert('Success', 'Article has been published.', 'success');

        return redirect(url($this->route));
    }

    /**
     * @param $slug
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getPreview($slug)
    {
        return $this->renderView('preview', [
            'result' => $this->repository->findBySlug($slug)
        ]);
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getEdit($id)
    {
        return $this->renderView('form', [
            'action' => 'update',
            'model'  => $this->repository->findById(decryptStringValue($id))
        ]);
    }

    /**
     * @param \App\Http\Requests\ArticleRequest $request
     * @param                                   $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postUpdate(ArticleRequest $request, $id)
    {
        $result = $this->repository->update($request, decryptStringValue($id));

        alert('Success', 'Article has been updated.', 'success');

        return redirect(url($this->route));
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function getDelete($id)
    {
        $result = $this->repository->delete(decryptStringValue($id));

        alert('Success', 'Article has been deleted.', 'success');

        return redirect(url($this->route));
    }

    /**
     * @return mixed
     */
    private function authorArray()
    {
        return Admin::pluck('name', 'id')->all();
    }
}
