<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\LandingContent;
use App\Models\LandingPage;
use App\Models\Language;
use App\Models\Page;
use DataTables;
use DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JoeDixon\Translation\Drivers\Translation;

class LanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Application|Factory|View
     */
    private $translation;

    private string $languageFilesPath;

    public function __construct(Translation $translation)
    {
        $this->middleware('permission:language-setting');
        $this->translation = $translation;
        $this->languageFilesPath = resource_path('lang');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Language::orderBy('created_at', 'asc');

            return Datatables::of($data)
                ->addIndexColumn()
                ->editColumn('name', 'backend.language.include.__name')
                ->editColumn('status', 'backend.language.include.__status')
                ->addColumn('action', 'backend.language.include.__action')
                ->rawColumns(['name', 'status', 'action'])
                ->make(true);
        }

        return view('backend.language.index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'code' => ['required', 'unique:languages,locale'],
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $input = $request->all();

        $data = [
            'name' => $input['name'],
            'locale' => $input['code'],
            'is_default' => $input['is_default'],
            'status' => $input['status'],
        ];

        if ($input['is_default']) {
            DB::table('languages')->update(['is_default' => 0]);
            $data['status'] = 1;
        }

        $this->translation->addLanguage($input['code'], $input['name']);

        language::create($data);

        $contents = LandingContent::where('locale', 'en')->get();

        foreach ($contents as $content) {
            $new = $content->replicate();
            $new->locale = $input['code'];
            $new->save();
        }

        $landingPages = LandingPage::where('locale', 'en')->get();

        foreach ($landingPages as $page) {
            $new = $page->replicate();
            $new->locale = $input['code'];
            $new->save();
        }

        $pages = Page::where('locale', 'en')->get();

        foreach ($pages as $page) {
            $new = $page->replicate();
            $new->locale = $input['code'];
            $new->save();
        }

        notify()->success(__('Language Added successfully'));

        return redirect()->route('admin.language.index');
    }

    /**
     * Update the specified resource in storage.
     *
     * @return RedirectResponse
     */
    public function update(Request $request, language $language)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'code' => ['required', 'unique:languages,locale,'.$language->id],
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $input = $request->all();

        $data = [
            'name' => $input['name'],
            'locale' => $input['code'],
            'is_default' => (bool) $input['is_default'],
            'status' => (bool) $input['status'],
        ];

        if ($language->is_default && ! $input['is_default']) {
            notify()->error('Please set default language', 'Error');

            return redirect()->back();
        }

        if ($input['is_default']) {
            DB::table('languages')->update(['is_default' => 0]);
            $data['status'] = 1;
        }

        /* Existing File name */
        $filePath = "{$this->languageFilesPath}".DIRECTORY_SEPARATOR."{$language->locale}.json";
        /* New File name */
        $newFileName = "{$this->languageFilesPath}".DIRECTORY_SEPARATOR."{$input['code']}.json";
        rename($filePath, $newFileName);

        /* Existing File name */
        $folderPath = "{$this->languageFilesPath}".DIRECTORY_SEPARATOR."{$language->locale}";
        /* New File name */
        $newFolderName = "{$this->languageFilesPath}".DIRECTORY_SEPARATOR."{$input['code']}";
        rename($folderPath, $newFolderName);

        $language->update($data);

        $contents = LandingContent::where('locale', $language->locale)->get();

        foreach ($contents as $content) {
            $new = $content;
            $new->locale = $input['code'];
            $new->save();
        }

        $landingPages = LandingPage::where('locale', $language->locale)->get();

        foreach ($landingPages as $page) {
            $new = $page;
            $new->locale = $input['code'];
            $new->save();
        }

        $pages = Page::where('locale', $language->locale)->get();

        foreach ($pages as $page) {
            $new = $page;
            $new->locale = $input['code'];
            $new->save();
        }

        notify()->success(__('Language Update successfully'));

        return redirect()->route('admin.language.index');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('backend.language.create');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Application|Factory|View
     */
    public function edit(language $language)
    {

        return view('backend.language.edit', compact('language'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return RedirectResponse
     */
    public function destroy(language $language)
    {
        \File::delete("{$this->languageFilesPath}".DIRECTORY_SEPARATOR."{$language->locale}.json");
        \File::deleteDirectory("{$this->languageFilesPath}".DIRECTORY_SEPARATOR."$language->locale");
        $language->delete();

        LandingPage::where('locale', $language->locale)->delete();
        LandingContent::where('locale', $language->locale)->delete();
        Page::where('locale', $language->locale)->delete();

        notify()->success(__('Language Delete successfully'));

        return redirect()->route('admin.language.index');
    }

    public function languageKeyword(Request $request, $language)
    {

        if ($request->has('language') && $request->get('language') !== $language) {
            return redirect()
                ->route('admin.language-keyword', ['language' => $request->get('language'), 'group' => $request->get('group'), 'filter' => $request->get('filter')]);
        }

        $languages = $this->translation->allLanguages();
        $groups = $this->translation->getGroupsFor(config('app.key_locale'))->merge('single');
        $translations = $this->translation->filterTranslationsFor($language, $request->get('filter'));

        if ($request->has('group') && $request->get('group')) {
            if ($request->get('group') === 'single') {
                $translations = $translations->get('single');
                $translations = new Collection(['single' => $translations]);
            } else {
                $translations = $translations->get('group')->filter(function ($values, $group) use ($request) {
                    return $group === $request->get('group');
                });

                $translations = new Collection(['group' => $translations]);
            }
        }

        return view('backend.language.keyword', compact('language', 'languages', 'groups', 'translations'));

    }

    public function keywordUpdate(Request $request)
    {
        $group = $request->group;
        $language = $request->language;
        $isGroupTranslation = ! Str::contains($group, 'single');

        $this->translation->add($request, $language, $isGroupTranslation);

        notify()->success(__('Keyword Updated'));

        return redirect()->back();
    }

    public function syncMissing()
    {
        \Artisan::call('translation:sync-missing-translation-keys');
        notify()->success(__('Successfully Sync Missing Translation Keys'));

        return redirect()->back();
    }
}
