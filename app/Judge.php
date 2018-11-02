<?php

namespace Toecyd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class Judge
 * @package Toecyd
 */
class Judge extends Model
{
    const JUDGES_PER_PAGE = 10;
    public $timestamps = false;

    // The attributes that are mass assignable.
    protected $fillable = [
        'surname',
        'name',
        'patronymic',
        'photo',
        'status',
		'updated_status',
		'due_date_status',
        'phone',
        'rating',
    ];

    public static function getJudgesListGuestFields() {
        return ['judges.id',
                'courts.name AS court_name',
                'judges.surname',
                'judges.name',
                'judges.patronymic',
                'judges.photo',
                'judges.status',
                DB::raw('DATE_FORMAT(judges.updated_status, "%d.%m.%Y") AS updated_status'),
                DB::raw('DATE_FORMAT(judges.due_date_status, "%d.%m.%Y") AS due_date_status'),
                'judges.rating'
        ];
    }

    public static function getSortVariants()
    {
        return [
        	0 => ['judges.id', 'ASC'],
            1 => ['judges.surname', 'ASC'],
            2 => ['judges.surname', 'DESC'],
            3 => ['judges.rating', 'ASC'],
            4 => ['judges.rating', 'DESC'],
        ];
    }

    public static function getJudgesListFields($user_id) {
        return array_merge(
            self::getJudgesListGuestFields(),
            [DB::raw("(CASE WHEN user_bookmark_judges.user = {$user_id} THEN 1 ELSE 0 END) AS is_bookmark")]
        );
    }

    /**
     * отримати список суддів, враховуючи фільтри, які були задані
     *
     * @param $regions         array
     * @param $instances       array
     * @param $jurisdictions   array
     * @param $sort_order      integer
     * @param $search          string
     * @param $powers_expired  boolean
     *
     * @return mixed
     */
    public static function getJudgesList($regions, $instances, $jurisdictions, $sort_order, $search, $powers_expired) {
        // отримання id користувача
        $user_id = Auth::check() ? Auth::user()->id : 0;

        // отримання способу сортування
        $sort_variant = (self::getSortVariants())[$sort_order];

        return (static::select(self::getJudgesListFields($user_id))
            ->join('courts', 'judges.court', '=', 'courts.court_code')
            ->leftJoin('user_bookmark_judges', function ($join) use ($user_id) {
                $join->on('judges.id', '=', 'user_bookmark_judges.judge');
                $join->on('user_bookmark_judges.user', '=', DB::raw($user_id));
            })
            // фільтрція за регіоном
            ->when(!empty($regions), function ($query) use ($regions) {
                return $query->whereIn('courts.region_code', $regions);
            })
            // фільтрція за інстанцією
            ->when(!empty($instances), function ($query) use ($instances) {
                return $query->whereIn('courts.instance_code', $instances);
            })
            // фільтрція за юрисдикцією
            ->when(!empty($jurisdictions), function ($query) use ($jurisdictions) {
                return $query->whereIn('courts.jurisdiction', $jurisdictions);
            })
            // якщо не переданий аргумент щоб показувати суддів в яких закінчились повноваження - значить упускємо їх при вибірці
            ->when($powers_expired == false, function ($query) {
                return $query->where('judges.status', '!=', 5);
            })
            // якщо застосовано пошук
            ->when(!empty($search), function ($query) use ($search) {
                return $query->where('judges.surname', 'LIKE', $search . '%');
            })
            ->orderBy($sort_variant[0], $sort_variant[1])
            ->paginate(self::JUDGES_PER_PAGE));
    }

    /**
     * Для не зареєстрованого користувача
     * отримати список суддів, враховуючи фільтри, які були задані
     *
     * @param $regions         array
     * @param $instances       array
     * @param $jurisdictions   array
     * @param $sort_order      integer
     * @param $search          string
     * @param $powers_expired  boolean
     *
     * @return mixed
     */
    public static function getJudgesListGuest($regions, $instances, $jurisdictions,
                                              $sort_order, $search, $powers_expired) {

        // отримання способу сортування
        $sort_variant = (self::getSortVariants())[$sort_order];

        return (static::select(self::getJudgesListGuestFields())
            ->join('courts', 'judges.court', '=', 'courts.court_code')
            // фільтрція за регіоном
            ->when(!empty($regions), function ($query) use ($regions) {
                return $query->whereIn('courts.region_code', $regions);
            })
            // фільтрція за інстанцією
            ->when(!empty($instances), function ($query) use ($instances) {
                return $query->whereIn('courts.instance_code', $instances);
            })
            // фільтрція за юрисдикцією
            ->when(!empty($jurisdictions), function ($query) use ($jurisdictions) {
                return $query->whereIn('courts.jurisdiction', $jurisdictions);
            })
            // якщо не переданий аргумент щоб показувати суддів в яких закінчились повноваження - значить упускємо їх при вибірці
            ->when($powers_expired == false, function ($query) {
                return $query->where('judges.status', '!=', 5);
            })
            // якщо застосовано пошук
            ->when(!empty($search), function ($query) use ($search) {
                return $query->where('judges.surname', 'LIKE', $search . '%');
            })
            ->orderBy($sort_variant[0], $sort_variant[1])
            ->paginate(self::JUDGES_PER_PAGE));
    }


    /**
     * отримує результати автодоповнення
     * для поля пошуку судді
     *
     * @param string $search
     *
     * @return mixed
     */
    public static function getAutocomplete(string $search) {

        $results = static::select('judges.id', 'judges.surname', 'judges.name', 'judges.patronymic')
            ->where('judges.surname', 'LIKE', $search . '%')
            ->limit(5)
            ->get();
        return ($results);

    }


    /**
     * встановлює новий статус судді
     *
     * @param $judge_id
     * @param $status
     * @param $due_date
     */
    public static function setNewStatus($judge_id, $status, $due_date) {
        static::where('judges.id', '=', $judge_id)
            ->update(['judges.status'          => $status,
                      'judges.due_date_status' => $due_date]);
    }


    /**
     * отримати дані по одному судді
     *
     * @param $judge_id
     */
    public static function getJudgeData($judge_id) {
        // отримання id користувача
        $user_id = Auth::check() ? Auth::user()->id : 0;
        return (static::select('judges.id', 'judges.surname', 'judges.name',
            'courts.name AS court_name', 'courts.address AS court_address',
            'courts.phone AS court_phone', 'courts.email AS court_email', 'courts.site AS court_site',
            'judges.patronymic', 'judges.photo', 'judges.status',
            DB::raw('(SELECT COUNT(*) FROM users_likes_judges WHERE users_likes_judges.judge=judges.id) AS likes'),
            DB::raw('(SELECT COUNT(*) FROM users_unlikes_judges WHERE users_unlikes_judges.judge=judges.id) AS unlikes'),
            DB::raw('DATE_FORMAT(judges.updated_status, "%d.%m.%Y") AS updated_status'),
            DB::raw('DATE_FORMAT(judges.due_date_status, "%d.%m.%Y") AS due_date_status'), 'judges.rating',
            DB::raw('(CASE WHEN user_bookmark_judges.user = ' . $user_id . ' THEN 1 ELSE 0 END) AS is_bookmark'))
            ->join('courts', 'judges.court', '=', 'courts.court_code')
            ->leftJoin('user_bookmark_judges', 'judges.id', '=', 'user_bookmark_judges.judge')
            ->where('judges.id', '=', $judge_id)
            ->first());
    }


    /**
     * перевірити чи існує суддя з даним id
     *
     * @param $id
     *
     * @return boolean
     */
    public static function checkJudgeById($id) {
        $judge = static::select('judges.id')
            ->where('judges.id', '=', $id)
            ->first();

        return !empty($judge);
    }
}