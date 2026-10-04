const userPagePhrases = {
    ar: {
        'Back to Dashboard': 'العودة إلى لوحة العيادة',
        'Request Implant Surgeon': 'طلب جرّاح زراعة',
        'Build the treatment plan and review its estimated price before submitting.': 'حدّد خطة العلاج وراجع السعر التقديري قبل إرسال الطلب.',
        'Surgical Service': 'الخدمة الجراحية',
        'What service does the patient need?': 'ما الخدمة التي يحتاجها المريض؟',
        'Required Service': 'الخدمة المطلوبة',
        'Select surgical service': 'اختر الخدمة الجراحية',
        'Dental Implant — زرع أسنان': 'زراعة أسنان',
        'Dental Implant Plan': 'خطة زراعة الأسنان',
        'Choose a regular implant package or plan each All-on arch separately.': 'اختر باقة زراعة عادية أو حدّد خطة كل فك من علاجات All-on بشكل منفصل.',
        'Treatment Type': 'نوع العلاج',
        'Select treatment type': 'اختر نوع العلاج',
        'All-on-4 / All-on-6 A to Z — choose each arch': 'All-on-4 / All-on-6 من البداية للنهاية — حدّد كل فك',
        'Implant Type': 'نوع الزرعة',
        'Select implant type': 'اختر نوع الزرعة',
        'Number of Implants': 'عدد الزرعات',
        'Who provides the implants?': 'من سيوفّر الزرعات؟',
        'Who provides this arch\'s implants?': 'من سيوفّر زرعات هذا الفك؟',
        'Select provider': 'اختر الجهة الموفّرة',
        'Easy Implant — We provide the implants': 'Easy Implant — نحن نوفّر الزرعات',
        'Clinic — You provide the implants': 'العيادة — أنت توفّر الزرعات',
        'Upper Arch': 'الفك العلوي',
        'Lower Arch': 'الفك السفلي',
        'All-on Package': 'باقة All-on',
        'No treatment for this arch': 'لا يوجد علاج لهذا الفك',
        'Professional work': 'أتعاب العمل الطبي',
        'Surgeon work': 'أتعاب الجرّاح',
        'Team work for selected arches': 'أتعاب الفريق للفكوك المحددة',
        'Implants supplied by us': 'الزرعات التي نوفرها',
        'Estimated total': 'الإجمالي التقديري',
        'Patient Details': 'بيانات المريض',
        'Patient Name': 'اسم المريض',
        'Patient Age': 'عمر المريض',
        'Medical History & Considerations': 'التاريخ المرضي والاعتبارات الطبية',
        'Proposed Operation Date': 'موعد العملية المقترح',
        'Choose a date at least 3 calendar days from today (Cairo time).': 'اختر موعدًا بعد 3 أيام تقويمية على الأقل من اليوم بتوقيت القاهرة.',
        'Additional Notes': 'ملاحظات إضافية',
        'Optional Clinical Files': 'ملفات الحالة الاختيارية',
        'Files upload directly from your browser to Cloudflare R2. They do not pass through the application server.': 'تُرفع الملفات مباشرة من متصفحك إلى مساحة التخزين الآمنة، ولا تمر عبر خادم التطبيق.',
        'CBCT Files': 'ملفات CBCT',
        'Lab Results': 'نتائج التحاليل',
        'Optional — multiple large files supported.': 'اختياري — يمكن رفع عدة ملفات كبيرة.',
        'Choose Files': 'اختيار الملفات',
        'Cancel': 'إلغاء',
        'Submit Request': 'إرسال الطلب',
        'Ready to upload': 'جاهز للرفع',
        'Uploading directly to Cloudflare R2...': 'جارٍ رفع الملف إلى مساحة التخزين الآمنة...',
        'Uploaded': 'تم الرفع',
        'Request Surgical Guide': 'طلب دليل جراحي',
        'Submit case details and upload scans to begin the planning process.': 'أدخل بيانات الحالة وارفع الأشعات لبدء مرحلة التخطيط.',
        'Type of Implant to be used': 'نوع الزرعة المستخدمة',
        'Other': 'نوع آخر',
        'Write implant type name': 'اكتب اسم نوع الزرعة',
        'Planned Operation Date': 'موعد العملية المخطط',
        'The operation date must be at least two days after submitting the request.': 'يجب أن يكون موعد العملية بعد يومين على الأقل من إرسال الطلب.',
        'Guided Kit': 'عدة الدليل الجراحي',
        'Select the implant type first, then tell us which guided kit will be used.': 'اختر نوع الزرعة أولًا، ثم حدّد عدة الدليل الجراحي المستخدمة.',
        'Rent a guided kit': 'استئجار عدة دليل جراحي',
        'Choose one of the kits available from Easy Implant.': 'اختر واحدة من العدد المتاحة لدى Easy Implant.',
        'I have my own kit': 'لديّ العدة الخاصة بي',
        'Enter its name and whether it is sleeved or sleeveless.': 'أدخل اسمها وحدّد هل هي مزودة بجلبة أم بدون جلبة.',
        'Available rental kit': 'العدة المتاحة للإيجار',
        'Select a guided kit': 'اختر عدة دليل جراحي',
        'No rental kits are available right now. Please choose “I have my own kit” or contact Easy Implant.': 'لا توجد عدة متاحة للإيجار الآن. اختر «لديّ العدة الخاصة بي» أو تواصل مع Easy Implant.',
        'Kit name': 'اسم العدة',
        'Enter the guided kit name': 'أدخل اسم عدة الدليل الجراحي',
        'Kit type': 'نوع العدة',
        'Select kit type': 'اختر نوع العدة',
        'Sleeved': 'مزودة بجلبة',
        'Sleeveless': 'بدون جلبة',
        'Kit photos': 'صور العدة',
        '(Optional)': '(اختياري)',
        'Upload clear photos of the guided kit and the components that will be used with the guide. For a Sleeved kit, include the sleeve and any drill keys/components if available. JPG, PNG, or WebP; up to 8 images and 10 MB each.': 'ارفع صورًا واضحة للعدة ومكوناتها المستخدمة مع الدليل. إذا كانت مزودة بجلبة، أرفق صورة الجلبة ومفاتيح الحفر أو المكونات المتاحة. يُسمح بحد أقصى 8 صور، وحجم 10 ميجابايت لكل صورة.',
        'Implant Locations': 'أماكن الزرعات',
        'Enter the number of implants needed in each area.': 'أدخل عدد الزرعات المطلوبة في كل منطقة.',
        'Delivery Method': 'طريقة التسليم',
        'I will print it at my clinic': 'سأطبع الدليل في عيادتي',
        'Admin will upload the Soft Guide (STL), Plan Video, and Instructions.': 'سترفع الإدارة ملف الدليل الرقمي (STL) وفيديو الخطة والتعليمات.',
        'Admin will print & deliver': 'ستطبع الإدارة الدليل وتوصّله',
        'Admin will upload the Plan Video and Instructions. Physical guide will be delivered.': 'سترفع الإدارة فيديو الخطة والتعليمات، وسيتم توصيل الدليل المطبوع.',
        'Upload CBCT Scan (DICOM/ZIP)': 'رفع أشعة CBCT بصيغة DICOM أو ZIP',
        'Browse CBCT': 'اختيار ملف CBCT',
        'Uploading CBCT...': 'جارٍ رفع ملف CBCT...',
        'Upload Intraoral Scan (STL/ZIP)': 'رفع المسح داخل الفم بصيغة STL أو ZIP',
        'Browse STL': 'اختيار ملف STL',
        'Uploading STL...': 'جارٍ رفع ملف STL...',
        'Additional Notes (Optional)': 'ملاحظات إضافية (اختياري)',
        'Any extra information for the planning team...': 'أي معلومات إضافية يحتاجها فريق التخطيط...',
        'Estimated Total': 'الإجمالي التقديري',
        'Protected Progress': 'الرصيد المرحّل',
        'Current': 'الحالي',
        'Free': 'المجاني',
        'Pricing Rule': 'قاعدة التسعير',
        'Clinic print first implant': 'أول زرعة عند طباعة العيادة',
        'Admin print first implant': 'أول زرعة عند طباعة الإدارة',
        'Additional implant': 'كل زرعة إضافية',
        'Your protected rule': 'قاعدتك المحفوظة',
        'Current progress': 'التقدم الحالي',
        'Next-cycle rule': 'قاعدة الدورة التالية',
        'Request Details': 'تفاصيل الطلب',
        'Request status:': 'حالة الطلب:',
        'Request Rejected': 'تم رفض الطلب',
        'Request progress': 'مراحل الطلب',
        'Surgeon Request Details': 'تفاصيل طلب الجرّاح',
        'Assigned Surgeon': 'الجرّاح المعيّن',
        'Online payment': 'الدفع الإلكتروني',
        'Pay now': 'ادفع الآن',
        'Online payment is temporarily unavailable.': 'الدفع الإلكتروني غير متاح مؤقتًا.',
        'Payment is not ready yet. Administration must confirm the surgeon, appointment and final price. Use the conversation to ask about the missing details.': 'الدفع غير جاهز بعد. يجب أن تؤكد الإدارة الجرّاح والموعد والسعر النهائي. استخدم المحادثة للاستفسار عن التفاصيل الناقصة.',
        'Conversation with Easy Implant': 'المحادثة مع Easy Implant',
        'Manual refresh only — no auto-update': 'التحديث يدوي — لا يوجد تحديث تلقائي',
        'Refresh': 'تحديث',
        'No messages yet. Send a note if you need clarification or changes.': 'لا توجد رسائل بعد. أرسل رسالة إذا احتجت توضيحًا أو تعديلًا.',
        'Write a message...': 'اكتب رسالة...',
        'Send message': 'إرسال الرسالة',
        'Operation coordination': 'تنسيق العملية',
        'Confirmed appointment (Cairo time)': 'الموعد المؤكد بتوقيت القاهرة',
        'Not assigned': 'لم يتم التعيين',
        'Not confirmed': 'لم يتم التأكيد',
        'Payment': 'الدفع',
        'Payment record': 'سجل الدفع',
        'Payment status': 'حالة الدفع',
        'Status': 'الحالة',
        'Confirmed On': 'تاريخ التأكيد',
        'Uploaded On': 'تاريخ الرفع',
        'No confirmed payment yet.': 'لا توجد دفعة مؤكدة حتى الآن.',
        'A completed online payment will appear here.': 'ستظهر هنا عملية الدفع الإلكتروني المكتملة.',
        'Delivery Package': 'ملفات التسليم',
        'The delivery package is not available.': 'ملفات التسليم غير متاحة.',
        'Open': 'فتح',
        'Conversation': 'المحادثة',
        'Refresh to see new replies': 'حدّث الصفحة لرؤية الردود الجديدة',
        'Clinic': 'العيادة',
        'Admin': 'الإدارة',
        'Check again': 'تحقق مرة أخرى',
        'Return to request': 'العودة إلى الطلب',
        'If your session has expired, sign in to return to this request.': 'إذا انتهت جلستك، سجّل الدخول للعودة إلى هذا الطلب.',
        'Reload this page to check the latest payment status.': 'أعد تحميل الصفحة للتحقق من أحدث حالة للدفع.'
        ,'Amount': 'المبلغ'
        ,'Open clinic dashboard': 'فتح لوحة العيادة'
        ,'Payment reference not found': 'لم يتم العثور على مرجع الدفع'
        ,'Open your clinic dashboard to review your requests.': 'افتح لوحة العيادة لمراجعة طلباتك.'
        ,'Payment confirmed': 'تم تأكيد الدفع'
        ,'Payment completed. Your operation booking is confirmed; awaiting the operation.': 'تم الدفع وتأكيد حجز العملية، والطلب الآن بانتظار موعد العملية.'
        ,'Payment completed. Your request is in progress.': 'تم الدفع وطلبك قيد التنفيذ.'
        ,'Payment was confirmed, but the operation was cancelled. Financial review is required; no automatic refund has been made.': 'تم تأكيد الدفع، لكن العملية أُلغيت. يلزم إجراء مراجعة مالية ولم يتم رد المبلغ تلقائيًا.'
        ,'Payment completed. Your request is completed.': 'تم الدفع واكتمل طلبك.'
        ,'Payment completed. Return to the request to review its current status.': 'تم الدفع. ارجع إلى الطلب لمراجعة حالته الحالية.'
        ,'Payment confirmation needs review': 'تأكيد الدفع يحتاج إلى مراجعة'
        ,'We could not verify the payment details. Contact support before making another payment.': 'تعذر التحقق من تفاصيل الدفع. تواصل مع الدعم قبل إجراء دفعة أخرى.'
        ,'Payment was not completed': 'لم تكتمل عملية الدفع'
        ,'This attempt was not completed. Return to your request to review the payment options.': 'لم تكتمل هذه المحاولة. ارجع إلى طلبك لمراجعة خيارات الدفع.'
        ,'Payment session expired': 'انتهت جلسة الدفع'
        ,'No payment has been confirmed for this session. Return to your request to review the payment options.': 'لم يتم تأكيد أي دفعة لهذه الجلسة. ارجع إلى طلبك لمراجعة خيارات الدفع.'
        ,'You returned before payment confirmation': 'عدت قبل تأكيد الدفع'
        ,'No payment has been confirmed yet. Use Check again if you completed a payment before returning.': 'لم يتم تأكيد الدفع بعد. استخدم «تحقق مرة أخرى» إذا أكملت الدفع قبل العودة.'
        ,'Payment is being verified': 'جارٍ التحقق من الدفع'
        ,'We are checking for payment confirmation. This page updates automatically.': 'نحن نتحقق من تأكيد الدفع. يتم تحديث هذه الصفحة تلقائيًا.'
        ,'Payment status is temporarily unavailable': 'حالة الدفع غير متاحة مؤقتًا'
        ,'Please check again shortly.': 'يرجى التحقق مرة أخرى بعد قليل.'
        ,'Confirmation is taking longer than expected. Use Check again, or return to your request.': 'تأكيد الدفع يستغرق وقتًا أطول من المتوقع. استخدم «تحقق مرة أخرى» أو ارجع إلى طلبك.'
        ,'Checking payment status…': 'جارٍ التحقق من حالة الدفع…'
        ,'Status updated.': 'تم تحديث الحالة.'
        ,'Checking automatically every few seconds.': 'يتم التحقق تلقائيًا كل عدة ثوانٍ.'
        ,'Could not check the status. Please check your connection and try again.': 'تعذر التحقق من الحالة. تحقق من اتصالك وحاول مرة أخرى.'
        ,'Dashboard': 'لوحة العيادة'
        ,'Logout': 'تسجيل الخروج'
        ,'Back to dashboard': 'العودة إلى لوحة العيادة'
        ,'Five protected stages': 'خمس مراحل واضحة'
        ,'Admin review': 'مراجعة الإدارة'
        ,'Your approval': 'موافقتك'
        ,'Production': 'التنفيذ'
        ,'Complete': 'مكتمل'
        ,'Surgical Guide Details': 'تفاصيل الدليل الجراحي'
        ,'Operation Date': 'موعد العملية'
        ,'Not specified': 'غير محدد'
        ,'Admin will print and deliver physical guide': 'ستطبع الإدارة الدليل وتوصّله'
        ,'CBCT Scan': 'أشعة CBCT'
        ,'Download / View': 'تحميل / عرض'
        ,'Intraoral Scan (STL)': 'المسح داخل الفم (STL)'
        ,'Source': 'المصدر'
        ,'Rental from Easy Implant': 'مستأجرة من Easy Implant'
        ,'Clinic-owned kit': 'عدة مملوكة للعيادة'
        ,'Kit Name': 'اسم العدة'
        ,'Kit Type': 'نوع العدة'
        ,'Not applicable': 'لا ينطبق'
        ,'Rental Price': 'سعر الإيجار'
        ,'View photo': 'عرض الصورة'
        ,'No kit photos were attached. Photos are optional.': 'لم تُرفق صور للعدة. الصور اختيارية.'
        ,'Notes': 'الملاحظات'
        ,'Case Review from Easy Implant': 'مراجعة الحالة من Easy Implant'
        ,'Review the explanation and every file in the latest round before approving the plan.': 'راجع الشرح وجميع ملفات أحدث مراجعة قبل الموافقة على الخطة.'
        ,'No review has been sent yet.': 'لم تُرسل مراجعة بعد.'
        ,'The administration is still reviewing your case and files.': 'ما زالت الإدارة تراجع الحالة والملفات.'
        ,'Your decision is needed on the latest review round.': 'مطلوب قرارك بشأن أحدث مراجعة.'
        ,'If you need changes, send your notes in the conversation. The request remains in this stage until you approve.': 'إذا احتجت تعديلات، أرسل ملاحظاتك في المحادثة. يظل الطلب في هذه المرحلة حتى توافق.'
        ,'Review round': 'جولة المراجعة'
        ,'Latest review': 'أحدث مراجعة'
        ,'Approved': 'تمت الموافقة'
        ,'This round contains files without an additional written explanation.': 'تحتوي هذه الجولة على ملفات بدون شرح كتابي إضافي.'
        ,'Your browser cannot preview this video.': 'لا يستطيع متصفحك معاينة هذا الفيديو.'
        ,'Approve the latest plan': 'الموافقة على أحدث خطة'
        ,'You will confirm this decision before it is saved.': 'سيُطلب منك تأكيد القرار قبل حفظه.'
        ,'Plan Videos': 'فيديوهات الخطة'
        ,'Instructions & Sheets': 'التعليمات والملفات'
        ,'Guide Files (STL)': 'ملفات الدليل (STL)'
        ,'Optional Files': 'ملفات اختيارية'
        ,'Download': 'تحميل'
        ,'Please contact the administration and mention request': 'تواصل مع الإدارة واذكر رقم الطلب'
        ,'No specific details found for this request.': 'لم يتم العثور على تفاصيل إضافية لهذا الطلب.'
        ,'Check whether a payment has been recorded for this request. Payment confirmation and operation completion are separate steps.': 'تحقق مما إذا تم تسجيل دفعة لهذا الطلب. تأكيد الدفع وإتمام العملية خطوتان منفصلتان.'
        ,'Paid online': 'مدفوع إلكترونيًا'
        ,'View receipt': 'عرض الإيصال'
        ,'Legacy receipt shown for historical reference only. New manual receipts are disabled.': 'يظهر هذا الإيصال القديم للرجوع إليه فقط. تم إيقاف الإيصالات اليدوية الجديدة.'
        ,'No payment is required at the review stage. After the surgeon, appointment and final price are confirmed, an Online payment section will become available.': 'لا يلزم الدفع في مرحلة المراجعة. بعد تأكيد الجرّاح والموعد والسعر النهائي سيظهر قسم الدفع الإلكتروني.'
        ,'Use the Online payment section above when the confirmed details are ready. Your payment record will appear here after confirmation.': 'استخدم قسم الدفع الإلكتروني بالأعلى بعد اكتمال التفاصيل المؤكدة. سيظهر سجل الدفع هنا بعد التأكيد.'
        ,'No confirmed payment is recorded here. If you have already paid, contact Easy Implant to check the payment status before paying again.': 'لا توجد دفعة مؤكدة مسجلة هنا. إذا كنت قد دفعت بالفعل، تواصل مع Easy Implant للتحقق قبل الدفع مرة أخرى.'
        ,'No confirmed payment is recorded for this request. Contact Easy Implant support if you need clarification about the payment records.': 'لا توجد دفعة مؤكدة مسجلة لهذا الطلب. تواصل مع دعم Easy Implant إذا احتجت توضيحًا.'
        ,'Open conversation': 'فتح المحادثة'
        ,'Click here to view chat': 'اضغط هنا لعرض المحادثة'
        ,'Close conversation': 'إغلاق المحادثة'
        ,'Select Refresh to check for new replies': 'اضغط تحديث للتحقق من الردود الجديدة'
        ,'Load new messages': 'تحميل الرسائل الجديدة'
        ,'Message Easy Implant administration about the case, appointment, price or requested changes. This conversation is for this request; replies appear when you select Refresh.': 'راسل إدارة Easy Implant بشأن الحالة أو الموعد أو السعر أو التعديلات المطلوبة. هذه المحادثة خاصة بهذا الطلب، وتظهر الردود عند الضغط على تحديث.'
        ,'This is the saved conversation with Easy Implant administration for this request. You can read previous messages; new messages are disabled because the request is closed.': 'هذه هي المحادثة المحفوظة مع إدارة Easy Implant لهذا الطلب. يمكنك قراءة الرسائل السابقة، وتم إيقاف الرسائل الجديدة لأن الطلب مغلق.'
        ,'This historical conversation is read-only. Contact Easy Implant support to confirm the next step for this request.': 'هذه المحادثة التاريخية للقراءة فقط. تواصل مع دعم Easy Implant لتأكيد الخطوة التالية.'
        ,'Chat messages': 'رسائل المحادثة'
        ,'Your message': 'رسالتك'
        ,'Write a message…': 'اكتب رسالة…'
        ,'This conversation is read-only because the request uses a previous status.': 'هذه المحادثة للقراءة فقط لأن الطلب يستخدم حالة قديمة.'
        ,'This conversation is read-only because the request is completed, rejected or cancelled.': 'هذه المحادثة للقراءة فقط لأن الطلب مكتمل أو مرفوض أو ملغي.'
        ,'What your request status means': 'ماذا تعني حالة الطلب؟'
        ,'What you need to do now': 'ما المطلوب منك الآن؟'
        ,'How your surgeon request works — view all 4 steps': 'كيف يعمل طلب الجرّاح — عرض المراحل الأربع'
        ,'Review & coordination': 'المراجعة والتنسيق'
        ,'Your request has been submitted. Easy Implant is reviewing the case and coordinating the surgeon, appointment and final price with your clinic.': 'تم إرسال طلبك. تراجع Easy Implant الحالة وتنسق مع عيادتك بشأن الجرّاح والموعد والسعر النهائي.'
        ,'Follow the conversation with Easy Implant and send any missing details, questions or requested changes. Payment becomes available after administration confirms the agreed details.': 'تابع المحادثة مع Easy Implant وأرسل أي بيانات ناقصة أو أسئلة أو تعديلات مطلوبة. يصبح الدفع متاحًا بعد تأكيد الإدارة للتفاصيل المتفق عليها.'
        ,'Awaiting your payment': 'بانتظار دفعك'
        ,'Your request is at the payment stage. Check the assigned surgeon, confirmed appointment and final total below before making payment.': 'وصل طلبك إلى مرحلة الدفع. راجع الجرّاح المعيّن والموعد المؤكد والإجمالي النهائي أدناه قبل الدفع.'
        ,'If the details are correct, use Pay now in the Online payment section. If you need a change, send a message to Easy Implant before paying. Full payment confirms your agreement to the displayed details.': 'إذا كانت التفاصيل صحيحة، استخدم «ادفع الآن» في قسم الدفع الإلكتروني. إذا احتجت تعديلًا، أرسل رسالة إلى Easy Implant قبل الدفع. يؤكد الدفع الكامل موافقتك على التفاصيل المعروضة.'
        ,'Paid — awaiting operation': 'تم الدفع — بانتظار العملية'
        ,'Payment has been confirmed. The request is awaiting the operation; this status does not mean the procedure has already taken place.': 'تم تأكيد الدفع والطلب بانتظار العملية. هذه الحالة لا تعني أن الإجراء تم بالفعل.'
        ,'Check the confirmed appointment below and use the conversation for any remaining arrangements. Administration records completion after the procedure.': 'راجع الموعد المؤكد أدناه واستخدم المحادثة لأي ترتيبات متبقية. تسجل الإدارة إتمام العملية بعد تنفيذها.'
        ,'Operation performed': 'تمت العملية'
        ,'Administration has marked this operation as completed. The request remains available as a record of the case, coordination and payment.': 'سجلت الإدارة العملية كمكتملة. يظل الطلب متاحًا كسجل للحالة والتنسيق والدفع.'
        ,'Review the recorded operation date and completion note below, where available. The conversation is now read-only. Contact Easy Implant support if you need further assistance.': 'راجع تاريخ العملية وملاحظة الإتمام أدناه عند توفرهما. أصبحت المحادثة للقراءة فقط. تواصل مع دعم Easy Implant إذا احتجت مساعدة إضافية.'
        ,'Request rejected': 'تم رفض الطلب'
        ,'Administration has declined this request. It will not continue through the booking steps.': 'رفضت الإدارة هذا الطلب، ولن يستمر في خطوات الحجز.'
        ,'Read the rejection reason shown on this page. The conversation is read-only; contact Easy Implant support if you need clarification.': 'اقرأ سبب الرفض الموضح في الصفحة. المحادثة للقراءة فقط، وتواصل مع الدعم إذا احتجت توضيحًا.'
        ,'Request cancelled': 'تم إلغاء الطلب'
        ,'This booking has been cancelled. The request and payment records are retained for reference.': 'تم إلغاء هذا الحجز، مع الاحتفاظ بسجلات الطلب والدفع للرجوع إليها.'
        ,'Read the cancellation reason and financial review notice below. Cancellation does not issue an automatic refund. The conversation is read-only; contact Easy Implant support about the financial review.': 'اقرأ سبب الإلغاء وتنبيه المراجعة المالية أدناه. الإلغاء لا يعيد المبلغ تلقائيًا. المحادثة للقراءة فقط، وتواصل مع الدعم بشأن المراجعة المالية.'
        ,'Previous coordination status': 'حالة تنسيق سابقة'
        ,'This request was saved with a coordination status from the previous workflow. The case details and conversation are retained for reference.': 'حُفظ هذا الطلب بحالة تنسيق من مسار العمل السابق، وتم الاحتفاظ بتفاصيل الحالة والمحادثة للرجوع إليها.'
        ,'Review the saved details below and contact Easy Implant support to confirm the next step. This historical conversation is read-only.': 'راجع التفاصيل المحفوظة أدناه وتواصل مع دعم Easy Implant لتأكيد الخطوة التالية. هذه المحادثة التاريخية للقراءة فقط.'
        ,'Request being followed up': 'الطلب قيد المتابعة'
        ,'This request uses a previous or unrecognized status. Review the saved case and coordination details below.': 'يستخدم هذا الطلب حالة سابقة أو غير معروفة. راجع تفاصيل الحالة والتنسيق المحفوظة أدناه.'
        ,'Contact Easy Implant to confirm the next step for this request.': 'تواصل مع Easy Implant لتأكيد الخطوة التالية لهذا الطلب.'
        ,'Payment is not ready yet because the surgeon, confirmed appointment or final price is missing. Use the conversation to ask Easy Implant to complete these details before you pay.': 'الدفع غير جاهز لأن الجرّاح أو الموعد المؤكد أو السعر النهائي ما زال ناقصًا. استخدم المحادثة واطلب من Easy Implant إكمال التفاصيل قبل الدفع.'
        ,'We review your case and agree the surgeon, appointment and price with you.': 'نراجع حالتك ونتفق معك على الجرّاح والموعد والسعر.'
        ,'Your payment': 'الدفع'
        ,'You review the confirmed details and pay the full final amount online.': 'تراجع التفاصيل المؤكدة وتدفع المبلغ النهائي كاملًا إلكترونيًا.'
        ,'Awaiting operation': 'بانتظار العملية'
        ,'Payment is confirmed and the operation is arranged for the confirmed appointment.': 'تم تأكيد الدفع وترتيب العملية في الموعد المؤكد.'
        ,'Administration records the procedure date and completion note.': 'تسجل الإدارة تاريخ الإجراء وملاحظة الإتمام.'
        ,'Current step': 'المرحلة الحالية'
        ,'These are the case details you submitted. Review them and use the conversation with Easy Implant to request any changes or clarify the treatment before payment.': 'هذه هي بيانات الحالة التي أرسلتها. راجعها واستخدم المحادثة مع Easy Implant لطلب أي تعديل أو توضيح العلاج قبل الدفع.'
        ,'Implant Package': 'باقة الزرعات'
        ,'Implant Provider': 'الجهة الموفّرة للزرعات'
        ,'Clinic — you provide the implants; their cost is excluded from this total': 'العيادة — أنت توفّر الزرعات، ولذلك لا تدخل تكلفتها في هذا الإجمالي'
        ,'Easy Implant — we provide the implants; their cost is included in this total': 'Easy Implant — نحن نوفّر الزرعات، ولذلك تدخل تكلفتها في هذا الإجمالي'
        ,'Implant type': 'نوع الزرعة'
        ,'Implants': 'الزرعات'
        ,'Provider': 'الجهة الموفّرة'
        ,'Team work': 'أتعاب الفريق'
        ,'Implant cost': 'تكلفة الزرعات'
        ,'Arch subtotal': 'إجمالي الفك'
        ,'Price breakdown': 'تفاصيل السعر'
        ,'Professional fees are for your selected treatment. Implant costs are added only for implants supplied by Easy Implant. Travel covers the visit to your clinic governorate and is charged once for the request.': 'الأتعاب المهنية تخص العلاج الذي اخترته. تضاف تكلفة الزرعات فقط عندما توفّرها Easy Implant. تغطي تكلفة الانتقال زيارة محافظة عيادتك وتُحسب مرة واحدة للطلب.'
        ,'Travel': 'الانتقال'
        ,'Final total': 'الإجمالي النهائي'
        ,'Administration has approved this final total. Payment is available at the payment stage after the surgeon and appointment are confirmed.': 'اعتمدت الإدارة هذا الإجمالي النهائي. يصبح الدفع متاحًا في مرحلة الدفع بعد تأكيد الجرّاح والموعد.'
        ,'This is the calculated estimate saved with your request. Administration must confirm the surgeon, appointment and final price before asking you to pay.': 'هذا هو التقدير المحسوب والمحفوظ مع طلبك. يجب أن تؤكد الإدارة الجرّاح والموعد والسعر النهائي قبل طلب الدفع.'
        ,'This service uses an individual quotation. Review the quoted total with Easy Implant and ask what it covers before paying.': 'تستخدم هذه الخدمة عرض سعر خاصًا. راجع الإجمالي مع Easy Implant واسأل عما يشمله قبل الدفع.'
        ,'This service has no automatic price. Administration reviews the case and provides an individual quotation; no payment is required while the quote is being prepared.': 'ليس لهذه الخدمة سعر تلقائي. تراجع الإدارة الحالة وتقدم عرض سعر خاصًا، ولا يلزم الدفع أثناء إعداد العرض.'
        ,'Each arch has its own treatment package, implant type and supplier. The arch subtotal combines team fees and any implants we supply for that arch; travel is added once to the whole request.': 'لكل فك باقة علاج ونوع زرعة وجهة توريد خاصة به. يجمع إجمالي الفك أتعاب الفريق وتكلفة أي زرعات نوفرها لهذا الفك، وتُضاف تكلفة الانتقال مرة واحدة للطلب كله.'
        ,'The package, implant type and number of implants describe the treatment you selected. The implant provider identifies who supplies the implants, separately from the surgeon professional fees.': 'توضح الباقة ونوع الزرعة وعدد الزرعات العلاج الذي اخترته. وتحدد الجهة الموفّرة للزرعات من سيوفرها بشكل منفصل عن أتعاب الجرّاح.'
        ,'Clinical Files': 'ملفات الحالة'
        ,'The CBCT scans and lab results attached to this request help administration review the case. Select a file to open or download it.': 'تساعد أشعات CBCT ونتائج التحاليل المرفقة الإدارة على مراجعة الحالة. اختر ملفًا لفتحه أو تحميله.'
        ,'No clinical files were attached to this request. Files are optional at submission; use the conversation to coordinate if administration needs more information.': 'لم تُرفق ملفات حالة بهذا الطلب. الملفات اختيارية عند الإرسال، ويمكن استخدام المحادثة للتنسيق إذا احتاجت الإدارة معلومات إضافية.'
        ,'The surgeon and appointment confirmed by Easy Implant for this request.': 'الجرّاح والموعد اللذان أكدتهما Easy Implant لهذا الطلب.'
        ,'Assigned surgeon': 'الجرّاح المعيّن'
        ,'Administration assigns the surgeon and confirms the appointment after coordinating with your clinic. The proposed date in your case details is your preference; the confirmed appointment here is the agreed date and time.': 'تعيّن الإدارة الجرّاح وتؤكد الموعد بعد التنسيق مع عيادتك. التاريخ المقترح في بيانات الحالة هو تفضيلك، أما الموعد المؤكد هنا فهو التاريخ والوقت المتفق عليهما.'
        ,'The surgeon or appointment has not been confirmed yet. Easy Implant needs to complete these details before payment can become available. Use the conversation for questions or changes.': 'لم يتم تأكيد الجرّاح أو الموعد بعد. تحتاج Easy Implant إلى إكمال هذه التفاصيل قبل إتاحة الدفع. استخدم المحادثة للأسئلة أو التعديلات.'
        ,'The request is marked paid, but the surgeon or confirmed appointment is missing. Ask Easy Implant to complete the coordination details.': 'الطلب مسجل كمدفوع، لكن الجرّاح أو الموعد المؤكد ناقص. اطلب من Easy Implant إكمال تفاصيل التنسيق.'
        ,'Discuss the case, appointment and price in the conversation. Administration confirms the agreed details before requesting payment.': 'ناقش الحالة والموعد والسعر في المحادثة. تؤكد الإدارة التفاصيل المتفق عليها قبل طلب الدفع.'
        ,'Review the surgeon, confirmed appointment and final price before payment. Ask for any changes in the conversation before paying. Full payment confirms your agreement to these details.': 'راجع الجرّاح والموعد المؤكد والسعر النهائي قبل الدفع. اطلب أي تعديلات في المحادثة قبل الدفع. يؤكد الدفع الكامل موافقتك على هذه التفاصيل.'
        ,'Payment confirmed — awaiting operation. Administration records completion after the procedure.': 'تم تأكيد الدفع — بانتظار العملية. تسجل الإدارة الإتمام بعد تنفيذ الإجراء.'
        ,'Historical completed request: operation date and completion note were not recorded.': 'طلب مكتمل قديم: لم يتم تسجيل تاريخ العملية أو ملاحظة الإتمام.'
        ,'Cancelled after payment — financial review required. No automatic refund has been made.': 'أُلغي بعد الدفع — يلزم إجراء مراجعة مالية. لم يتم رد المبلغ تلقائيًا.'
        ,'Financial review required. No automatic refund has been made.': 'تلزم مراجعة مالية. لم يتم رد المبلغ تلقائيًا.'
        ,'Full payment confirms your agreement to the surgeon, appointment and final price shown above.': 'يؤكد الدفع الكامل موافقتك على الجرّاح والموعد والسعر النهائي الموضحة أعلاه.'
        ,'Select Pay now to open secure online checkout. After paying, return to this request and check the payment record. Your request moves to Paid — awaiting operation after payment confirmation.': 'اختر «ادفع الآن» لفتح صفحة الدفع الآمنة. بعد الدفع، ارجع إلى الطلب وتحقق من سجل الدفع. ينتقل الطلب إلى «تم الدفع — بانتظار العملية» بعد تأكيد الدفع.'
        ,'The operation details changed. Review the updated surgeon, appointment and final price before paying.': 'تغيرت تفاصيل العملية. راجع الجرّاح والموعد والسعر النهائي المحدثة قبل الدفع.'
        ,'Payment could not be started. Please try again or contact support.': 'تعذر بدء الدفع. حاول مرة أخرى أو تواصل مع الدعم.'
        ,'Upper Anterior': 'المنطقة الأمامية العلوية'
        ,'Upper Right Posterior': 'المنطقة الخلفية العلوية اليمنى'
        ,'Upper Left Posterior': 'المنطقة الخلفية العلوية اليسرى'
        ,'Lower Anterior': 'المنطقة الأمامية السفلية'
        ,'Lower Right Posterior': 'المنطقة الخلفية السفلية اليمنى'
        ,'Lower Left Posterior': 'المنطقة الخلفية السفلية اليسرى'
        ,'Dr.': 'د.'
        ,'has been assigned to this case.': 'تم تعيينه لهذه الحالة.'
        ,'The patient this surgical request is for.': 'المريض الذي يخصه هذا الطلب الجراحي.'
        ,'The patient age in years, as entered when you submitted the request.': 'عمر المريض بالسنوات كما أدخلته عند إرسال الطلب.'
        ,'The surgical service you requested for this case.': 'الخدمة الجراحية التي طلبتها لهذه الحالة.'
        ,'Your preferred date when submitting the request. Check Operation coordination below for the appointment confirmed by administration.': 'التاريخ الذي فضّلته عند إرسال الطلب. راجع «تنسيق العملية» أدناه لمعرفة الموعد الذي أكدته الإدارة.'
        ,'The medical information you submitted for administration to review this case.': 'المعلومات الطبية التي أرسلتها لتراجع الإدارة الحالة.'
        ,'The extra instructions or information you included with your request.': 'التعليمات أو المعلومات الإضافية التي أرفقتها بالطلب.'
        ,'Surgical Guide progress': 'تقدم طلب الدليل الجراحي'
        ,'Every': 'كل'
        ,'Travel to': 'الانتقال إلى'
        ,'Cairo': 'القاهرة'
        ,'Giza': 'الجيزة'
        ,'Alexandria': 'الإسكندرية'
        ,'Dakahlia': 'الدقهلية'
        ,'Red Sea': 'البحر الأحمر'
        ,'Beheira': 'البحيرة'
        ,'Fayoum': 'الفيوم'
        ,'Gharbia': 'الغربية'
        ,'Ismailia': 'الإسماعيلية'
        ,'Menofia': 'المنوفية'
        ,'Minya': 'المنيا'
        ,'Qalyubia': 'القليوبية'
        ,'New Valley': 'الوادي الجديد'
        ,'Suez': 'السويس'
        ,'Aswan': 'أسوان'
        ,'Assiut': 'أسيوط'
        ,'Beni Suef': 'بني سويف'
        ,'Port Said': 'بورسعيد'
        ,'Damietta': 'دمياط'
        ,'Sharqia': 'الشرقية'
        ,'South Sinai': 'جنوب سيناء'
        ,'Kafr El Sheikh': 'كفر الشيخ'
        ,'Matrouh': 'مطروح'
        ,'Luxor': 'الأقصر'
        ,'Qena': 'قنا'
        ,'North Sinai': 'شمال سيناء'
        ,'Sohag': 'سوهاج'
        ,'Uploaded file': 'ملف مرفوع'
        ,'Size unavailable': 'الحجم غير متاح'
        ,'MP4 video': 'فيديو MP4'
        ,'JPEG image': 'صورة JPEG'
        ,'PNG image': 'صورة PNG'
        ,'WebP image': 'صورة WebP'
        ,'ZIP archive': 'أرشيف ZIP'
        ,'RAR archive': 'أرشيف RAR'
        ,'STL model': 'نموذج STL'
        ,'ZIP file': 'ملف ZIP'
        ,'RAR file': 'ملف RAR'
        ,'STL file': 'ملف STL'
        ,'File': 'ملف'
    }
};
