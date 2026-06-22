<?php

namespace {
    if (!function_exists('get_user')) {
        function get_user()
        {
            return $GLOBALS['__page_test_user'];
        }
    }

    if (!function_exists('auth_at_least')) {
        function auth_at_least($role)
        {
            return auth_user_at_least(get_user(), $role);
        }
    }

    if (!function_exists('auth_require_at_least')) {
        function auth_require_at_least($role)
        {
            if (!auth_at_least($role)) {
                die('Unauthorized access');
            }
        }
    }

    if (!function_exists('has_shift_permissions')) {
        function has_shift_permissions(\Shift $shift)
        {
            $user = get_user();
            return match ($user->role) {
                ROLE_SUDO, ROLE_PROF => true,
                ROLE_TA => $shift->prof == $user,
                ROLE_STUDENT => $user->groups->exists(fn ($key, $group) => $group->shift == $shift),
            };
        }
    }

    if (!function_exists('has_group_permissions')) {
        function has_group_permissions(\ProjGroup $group)
        {
            $user = get_user();
            return match ($user->role) {
                ROLE_SUDO, ROLE_PROF => true,
                ROLE_TA => $group->shift->prof == $user,
                ROLE_STUDENT => $user->groups->contains($group),
            };
        }
    }

    if (!function_exists('dourl')) {
        function dourl($page, $args = [])
        {
            $args['page'] = $page;
            return 'index.php?' . http_build_query($args, '', '&');
        }
    }

    if (!function_exists('dolink_ext')) {
        function dolink_ext($url, $txt)
        {
            return ['label' => $txt, 'url' => (string)$url];
        }
    }

    if (!function_exists('dolink')) {
        function dolink($page, $txt, $args = [])
        {
            return dolink_ext(dourl($page, $args), $txt);
        }
    }

    if (!function_exists('dolink_group')) {
        function dolink_group(\ProjGroup $group, $txt)
        {
            return dolink('listproject', $txt, ['id' => $group->id]);
        }
    }

    if (!function_exists('link_patch')) {
        function link_patch(\Patch $patch)
        {
            return 'https://localhost/' . dourl('editpatch', ['id' => $patch->id]);
        }
    }

    if (!function_exists('terminate')) {
        function terminate($error_message = null, $template = 'main.html.twig', $extra_fields = [])
        {
            throw new \Tests\Integration\Pages\PageTerminated($error_message, $template, $extra_fields);
        }
    }

    if (!function_exists('terminate_redirect')) {
        function terminate_redirect()
        {
            throw new \Tests\Integration\Pages\PageRedirected($_SERVER['REQUEST_URI'] ?? '');
        }
    }

    if (!function_exists('handle_form')) {
        function handle_form(&$obj, $hide_fields, $readonly, $only_fields = null, $in_required = null)
        {
            global $form, $formFactory, $request, $success_message;

            $builder = $formFactory->createBuilder(
                \Symfony\Component\Form\Extension\Core\Type\FormType::class
            );
            $editable = false;
            $fieldNames = [];

            foreach (get_object_vars($obj) as $name => $value) {
                if (in_array($name, $hide_fields, true) ||
                    ($only_fields && !in_array($name, $only_fields, true))) {
                    continue;
                }

                $fieldNames[] = $name;
                $disabled = in_array($name, $readonly, true);
                $editable = $editable || !$disabled;
                $required = $in_required ? in_array($name, $in_required, true) : true;
                $label = strtr($name, '_', ' ');

                if (is_bool($value)) {
                    $type = \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class;
                    $options = [
                        'label' => $label,
                        'data' => $value,
                        'required' => false,
                        'disabled' => $disabled,
                    ];
                } elseif (is_int($value)) {
                    $type = \Symfony\Component\Form\Extension\Core\Type\IntegerType::class;
                    $options = [
                        'label' => $label,
                        'data' => $value,
                        'required' => $required,
                        'disabled' => $disabled,
                    ];
                } elseif ($value instanceof \DateTimeInterface) {
                    $type = \Symfony\Component\Form\Extension\Core\Type\DateTimeType::class;
                    $options = [
                        'label' => $label,
                        'data' => $value,
                        'input' => 'datetime_immutable',
                        'widget' => 'single_text',
                        'required' => $required,
                        'disabled' => $disabled,
                    ];
                } elseif ($value instanceof \UnitEnum) {
                    $type = \Symfony\Component\Form\Extension\Core\Type\EnumType::class;
                    $options = [
                        'class' => get_class($value),
                        'label' => $label,
                        'data' => $value,
                        'required' => $required,
                        'disabled' => $disabled,
                    ];
                } else {
                    $stringValue = (string)$value;
                    $type = str_contains($name, 'url') || str_starts_with($stringValue, 'https://')
                        ? \Symfony\Component\Form\Extension\Core\Type\UrlType::class
                        : \Symfony\Component\Form\Extension\Core\Type\TextType::class;
                    $options = [
                        'label' => $label,
                        'data' => $stringValue,
                        'required' => $required,
                        'disabled' => $disabled,
                    ];
                }

                $builder->add($name, $type, $options);
            }

            $builder->add('submit', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, [
                'label' => 'Save changes',
                'disabled' => !$editable,
            ]);

            $form = $builder->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                foreach ($fieldNames as $name) {
                    if (in_array($name, $readonly, true)) {
                        continue;
                    }

                    $value = $form->get($name)->getData() ?? '';
                    $setter = "set_$name";
                    if (method_exists($obj, $setter)) {
                        $obj->$setter($value);
                    } else {
                        $obj->$name = $value;
                    }
                }
                $success_message = 'Database updated!';
            }
        }
    }

    if (!function_exists('filter_by')) {
        function filter_by($filters, $extra_filters = [])
        {
            return $GLOBALS['__page_test_filter_result'] ?? [];
        }
    }

    if (!function_exists('mk_eval_box')) {
        function mk_eval_box(int $year, ?string $page, ?\User $student, ?\ProjGroup $group)
        {
            $GLOBALS['__page_test_eval_boxes'][] = compact('year', 'page', 'student', 'group');
        }
    }

    if (!function_exists('get_term_for')) {
        function get_term_for($year)
        {
            return "$year/" . ($year + 1);
        }
    }

    if (!function_exists('markdown_to_html')) {
        function markdown_to_html($text)
        {
            return $text;
        }
    }

    if (!function_exists('validate_role')) {
        function validate_role($role, $allow_sudo)
        {
            return is_int($role) &&
                $role >= ($allow_sudo ? ROLE_SUDO : ROLE_PROF) &&
                $role <= ROLE_STUDENT;
        }
    }

    if (!function_exists('auth_set_user')) {
        function auth_set_user($user)
        {
            $GLOBALS['__page_test_auth_set_user'][] = $user;
            $GLOBALS['__page_test_user'] = $user;
        }
    }
}

namespace Tests\Integration\Pages {
    use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
    use Symfony\Component\Form\Forms;
    use Symfony\Component\HttpFoundation\Request;
    use Tests\Integration\Base\IntegrationTestCase;

    class PageTerminated extends \RuntimeException
    {
        public function __construct(
            public readonly ?string $errorMessage = null,
            public readonly string $template = 'main.html.twig',
            public readonly array $extraFields = []
        ) {
            parent::__construct($errorMessage ?? '');
        }
    }

    class PageRedirected extends \RuntimeException
    {
        public function __construct(public readonly string $url)
        {
            parent::__construct($url);
        }
    }

    abstract class PageTestCase extends IntegrationTestCase
    {
        private array $originalRuntimeGlobals = [];

        protected function setUp(): void
        {
            parent::setUp();

            foreach (['_GET', '_POST', '_REQUEST', '_SESSION', '_SERVER', 'page'] as $name) {
                $this->originalRuntimeGlobals[$name] = [
                    'exists' => array_key_exists($name, $GLOBALS),
                    'value' => $GLOBALS[$name] ?? null,
                ];
            }

            $_SERVER['HTTP_HOST'] = 'localhost';
        }

        protected function tearDown(): void
        {
            unset(
                $GLOBALS['__page_test_user'],
                $GLOBALS['__page_test_filter_result'],
                $GLOBALS['__page_test_eval_boxes'],
                $GLOBALS['__page_test_auth_set_user'],
                $GLOBALS['__page_test_saved_bugs'],
                $GLOBALS['__page_test_deadline_year'],
                $GLOBALS['__page_test_shifts_year'],
                $GLOBALS['__patches_page_create_patch'],
                $GLOBALS['__patches_page_save_patch'],
                $GLOBALS['__patches_page_email_ta'],
                $GLOBALS['__editpatch_page_email_ta'],
                $GLOBALS['__editpatch_page_email_group'],
                $GLOBALS['__patches_email'],
                $GLOBALS['__editpatch_emails'],
                $GLOBALS['patch']
            );

            foreach ($this->originalRuntimeGlobals as $name => $original) {
                if ($original['exists']) {
                    $GLOBALS[$name] = $original['value'];
                } else {
                    unset($GLOBALS[$name]);
                }
            }
            $this->originalRuntimeGlobals = [];

            parent::tearDown();
        }

        protected function createPageUser(
            string $id = 'student',
            string $name = 'Test Student',
            int $role = ROLE_STUDENT
        ): \User {
            return new \User($id, $name, "$id@example.com", '', $role, false);
        }

        protected function createPageGroup(
            int $number,
            int $year,
            array $students,
            ?\User $prof = null,
            ?int $groupId = null,
            ?int $shiftId = null,
            string $repository = ''
        ): \ProjGroup {
            $shift = new \Shift('T1', $year);
            if ($shiftId !== null) {
                $shift->id = $shiftId;
            }
            $shift->prof = $prof;

            $group = new \ProjGroup($number, $year, $shift);
            if ($groupId !== null) {
                $group->id = $groupId;
            }
            $group->repository = $repository;

            foreach ($students as $student) {
                $group->addStudent($student);
            }

            return $group;
        }

        protected function setUpPageRuntime(
            string $page,
            ?\User $user = null,
            string $method = 'GET',
            array $query = [],
            array $requestData = []
        ): void {
            global $formFactory, $request;
            global $custom_header, $form, $select_form, $comments_form;
            global $eval_forms, $copy_form, $embed_file, $info_message;
            global $success_message, $table, $lists, $deadline, $top_box;
            global $info_box, $monospace, $bottom_links, $refresh_url;
            global $confirm, $large_video, $comments, $ci_failures;
            global $display_formula, $plots, $milestones;

            $GLOBALS['page'] = $page;
            $GLOBALS['__page_test_user'] = $user ?? $this->createPageUser();

            $_GET = ['page' => $page] + $query;
            $_POST = $method === 'POST' ? $requestData : [];
            $_REQUEST = $_GET + $_POST;

            $request = Request::create(
                '/index.php?page=' . $page,
                $method,
                $method === 'POST' ? $_POST : $_GET
            );
            $formFactory = Forms::createFormFactoryBuilder()
                ->addExtension(new HttpFoundationExtension())
                ->getFormFactory();

            $custom_header = null;
            $form = null;
            $select_form = null;
            $comments_form = null;
            $eval_forms = [];
            $copy_form = null;
            $embed_file = null;
            $info_message = null;
            $success_message = null;
            $table = null;
            $lists = null;
            $deadline = null;
            $top_box = null;
            $info_box = null;
            $monospace = null;
            $bottom_links = null;
            $refresh_url = null;
            $confirm = null;
            $large_video = null;
            $comments = null;
            $ci_failures = null;
            $display_formula = null;
            $plots = null;
            $milestones = null;
        }

        protected function runPage(string $page): void
        {
            global $formFactory, $request;
            global $custom_header, $form, $select_form, $comments_form;
            global $eval_forms, $copy_form, $embed_file, $info_message;
            global $success_message, $table, $lists, $deadline, $top_box;
            global $info_box, $monospace, $bottom_links, $refresh_url;
            global $confirm, $large_video, $comments, $ci_failures;
            global $display_formula, $plots, $milestones;

            require dirname(__DIR__, 3) . "/pages/$page.php";
        }
    }
}
