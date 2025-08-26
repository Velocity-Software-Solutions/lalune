<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted'             => 'يجب قبول حقل :attribute.',
    'accepted_if'          => 'يجب قبول حقل :attribute عندما يكون :other هو :value.',
    'active_url'           => 'يجب أن يكون حقل :attribute رابط URL صالح.',
    'after'                => 'يجب أن يكون حقل :attribute تاريخًا بعد :date.',
    'after_or_equal'       => 'يجب أن يكون حقل :attribute تاريخًا بعد أو يساوي :date.',
    'alpha'                => 'يجب أن يحتوي حقل :attribute على أحرف فقط.',
    'alpha_dash'           => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_num'            => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام فقط.',
    'any_of'               => 'حقل :attribute غير صالح.',
    'array'                => 'يجب أن يكون حقل :attribute مصفوفة.',
    'ascii'                => 'يجب أن يحتوي حقل :attribute على رموز وأحرف أبجدية رقمية أحادية البايت فقط.',
    'before'               => 'يجب أن يكون حقل :attribute تاريخًا قبل :date.',
    'before_or_equal'      => 'يجب أن يكون حقل :attribute تاريخًا قبل أو يساوي :date.',
    'between' => [
        'array'   => 'يجب أن يحتوي حقل :attribute على ما بين :min و :max عنصر.',
        'file'    => 'يجب أن يكون حجم ملف :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'string'  => 'يجب أن يحتوي حقل :attribute على ما بين :min و :max حرفًا.',
    ],
    'boolean'              => 'يجب أن تكون قيمة حقل :attribute إما صحيحة أو خاطئة.',
    'can'                  => 'يحتوي حقل :attribute على قيمة غير مصرح بها.',
    'confirmed'            => 'تأكيد حقل :attribute غير متطابق.',
    'contains'              => 'يفتقد حقل :attribute إلى قيمة مطلوبة.',
    'current_password'     => 'كلمة المرور غير صحيحة.',
    'date'                 => 'يجب أن يكون حقل :attribute تاريخًا صالحًا.',
    'date_equals'          => 'يجب أن يكون حقل :attribute تاريخًا مساويًا لـ :date.',
    'date_format'          => 'يجب أن يتطابق حقل :attribute مع التنسيق :format.',
    'decimal'              => 'يجب أن يحتوي حقل :attribute على :decimal منازل عشرية.',
    'declined'             => 'يجب رفض حقل :attribute.',
    'declined_if'          => 'يجب رفض حقل :attribute عندما يكون :other هو :value.',
    'different'            => 'يجب أن يختلف حقل :attribute عن :other.',
    'digits'               => 'يجب أن يحتوي حقل :attribute على :digits أرقام.',
    'digits_between'       => 'يجب أن يحتوي حقل :attribute بين :min و :max رقمًا.',
    'dimensions'           => 'أبعاد الصورة في حقل :attribute غير صالحة.',
    'distinct'             => 'يحتوي حقل :attribute على قيمة مكررة.',
    'doesnt_end_with'      => 'يجب ألا ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'doesnt_start_with'    => 'يجب ألا يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'email'                => 'يجب أن يكون حقل :attribute بريدًا إلكترونيًا صالحًا.',
    'ends_with'            => 'يجب أن ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'enum'                 => 'القيمة المحددة في :attribute غير صالحة.',
    'exists'               => 'القيمة المحددة في :attribute غير صالحة.',
    'extensions'           => 'يجب أن يحتوي حقل :attribute على أحد الامتدادات التالية: :values.',
    'file'                 => 'يجب أن يكون حقل :attribute ملفًا.',
    'filled'               => 'يجب أن يحتوي حقل :attribute على قيمة.',
    'gt' => [
        'array'   => 'يجب أن يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file'    => 'يجب أن يكون حجم ملف :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
        'string'  => 'يجب أن يحتوي حقل :attribute على أكثر من :value حرفًا.',
    ],
    'gte' => [
        'array'   => 'يجب أن يحتوي حقل :attribute على :value عنصر أو أكثر.',
        'file'    => 'يجب أن يكون حجم ملف :attribute أكبر من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من أو تساوي :value.',
        'string'  => 'يجب أن يحتوي حقل :attribute على أكثر من أو يساوي :value حرفًا.',
    ],
    'hex_color'            => 'يجب أن يكون حقل :attribute لونًا سداسيًا صالحًا.',
    'image'                => 'يجب أن يكون حقل :attribute صورة.',
    'in'                   => 'القيمة المحددة في :attribute غير صالحة.',
    'in_array'             => 'يجب أن يحتوي حقل :attribute على قيمة موجودة في :other.',
    'in_array_keys'        => 'يجب أن يحتوي حقل :attribute على مفتاح واحد على الأقل من: :values.',
    'integer'              => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'ip'                   => 'يجب أن يكون حقل :attribute عنوان IP صالح.',
    'ipv4'                 => 'يجب أن يكون حقل :attribute عنوان IPv4 صالح.',
    'ipv6'                 => 'يجب أن يكون حقل :attribute عنوان IPv6 صالح.',
    'json'                 => 'يجب أن يكون حقل :attribute نص JSON صالح.',
    'list'                 => 'يجب أن يكون حقل :attribute قائمة.',
    'lowercase'            => 'يجب أن يكون حقل :attribute بأحرف صغيرة.',
    'lt' => [
        'array'   => 'يجب أن يحتوي حقل :attribute على أقل من :value عنصر.',
        'file'    => 'يجب أن يكون حجم ملف :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من :value.',
        'string'  => 'يجب أن يحتوي حقل :attribute على أقل من :value حرفًا.',
    ],
    'lte' => [
        'array'   => 'يجب ألا يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file'    => 'يجب أن يكون حجم ملف :attribute أقل من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من أو تساوي :value.',
        'string'  => 'يجب أن يحتوي حقل :attribute على أقل من أو يساوي :value حرفًا.',
    ],
    'mac_address'          => 'يجب أن يكون حقل :attribute عنوان MAC صالح.',
    'max' => [
        'array'   => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عنصر.',
        'file'    => 'يجب ألا يكون حجم ملف :attribute أكبر من :max كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.',
        'string'  => 'يجب ألا يحتوي حقل :attribute على أكثر من :max حرفًا.',
    ],
    'max_digits'           => 'يجب ألا يحتوي حقل :attribute على أكثر من :max رقم.',
    'mimes'                => 'يجب أن يكون حقل :attribute ملفًا من النوع: :values.',
    'mimetypes'            => 'يجب أن يكون حقل :attribute ملفًا من النوع: :values.',
    'min' => [
        'array'   => 'يجب أن يحتوي حقل :attribute على الأقل على :min عناصر.',
        'file'    => 'يجب أن يكون حجم ملف :attribute على الأقل :min كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute على الأقل :min.',
        'string'  => 'يجب أن يحتوي حقل :attribute على الأقل على :min أحرف.',
    ],
    'min_digits'           => 'يجب أن يحتوي حقل :attribute على الأقل على :min أرقام.',
    'missing'               => 'يجب أن يكون حقل :attribute مفقودًا.',
    'missing_if'            => 'يجب أن يكون حقل :attribute مفقودًا عندما يكون :other هو :value.',
    'missing_unless'        => 'يجب أن يكون حقل :attribute مفقودًا ما لم يكن :other هو :value.',
    'missing_with'          => 'يجب أن يكون حقل :attribute مفقودًا عند وجود :values.',
    'missing_with_all'      => 'يجب أن يكون حقل :attribute مفقودًا عند وجود :values جميعها.',
    'multiple_of'           => 'يجب أن يكون حقل :attribute مضاعفًا لـ :value.',
    'not_in'                => 'القيمة المحددة في :attribute غير صالحة.',
    'not_regex'             => 'تنسيق حقل :attribute غير صالح.',
    'numeric'               => 'يجب أن يكون حقل :attribute رقمًا.',
    'password' => [
        'letters'       => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed'         => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers'       => 'يجب أن يحتوي حقل :attribute على رقم واحد على الأقل.',
        'symbols'       => 'يجب أن يحتوي حقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت قيمة :attribute في تسريب بيانات. يرجى اختيار :attribute آخر.',
    ],
    'present'               => 'يجب أن يكون حقل :attribute موجودًا.',
    'present_if'            => 'يجب أن يكون حقل :attribute موجودًا عندما يكون :other هو :value.',
    'present_unless'        => 'يجب أن يكون حقل :attribute موجودًا ما لم يكن :other هو :value.',
    'present_with'          => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values.',
    'present_with_all'      => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values جميعها.',
    'prohibited'            => 'حقل :attribute ممنوع.',
    'prohibited_if'         => 'حقل :attribute ممنوع عندما يكون :other هو :value.',
    'prohibited_if_accepted'=> 'حقل :attribute ممنوع عندما يتم قبول :other.',
    'prohibited_if_declined'=> 'حقل :attribute ممنوع عندما يتم رفض :other.',
    'prohibited_unless'     => 'حقل :attribute ممنوع ما لم يكن :other في :values.',
    'prohibits'             => 'حقل :attribute يمنع وجود :other.',
    'regex'                 => 'تنسيق حقل :attribute غير صالح.',
    'required'              => 'حقل :attribute مطلوب.',
    'required_array_keys'   => 'يجب أن يحتوي حقل :attribute على مفاتيح: :values.',
    'required_if'           => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted'  => 'حقل :attribute مطلوب عندما يتم قبول :other.',
    'required_if_declined'  => 'حقل :attribute مطلوب عندما يتم رفض :other.',
    'required_unless'       => 'حقل :attribute مطلوب ما لم يكن :other في :values.',
    'required_with'         => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all'     => 'حقل :attribute مطلوب عند وجود :values جميعها.',
    'required_without'      => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all'  => 'حقل :attribute مطلوب عند عدم وجود أي من :values.',
    'same'                  => 'يجب أن يتطابق حقل :attribute مع :other.',
    'size' => [
        'array'   => 'يجب أن يحتوي حقل :attribute على :size عناصر.',
        'file'    => 'يجب أن يكون حجم ملف :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string'  => 'يجب أن يحتوي حقل :attribute على :size أحرف.',
    ],
    'starts_with'           => 'يجب أن يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'string'                => 'يجب أن يكون حقل :attribute نصًا.',
    'timezone'              => 'يجب أن يكون حقل :attribute منطقة زمنية صالحة.',
    'unique'                => 'تم أخذ :attribute بالفعل.',
    'uploaded'              => 'فشل في تحميل :attribute.',
    'uppercase'             => 'يجب أن يكون حقل :attribute بأحرف كبيرة.',
    'url'                   => 'يجب أن يكون حقل :attribute رابط URL صالح.',
    'ulid'                  => 'يجب أن يكون حقل :attribute ULID صالح.',
    'uuid'                  => 'يجب أن يكون حقل :attribute UUID صالح.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'رسالة مخصصة',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [],

];
