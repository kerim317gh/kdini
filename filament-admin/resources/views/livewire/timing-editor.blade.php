<div>
    <div id='timing-editor-modal'>
        <h2 class='text-2xl font-bold mb-4'>ویرایشگر زمان‌بندی: {{ $audio->title }}</h2>

        @if($audio->url)
            <audio id='audioPlayer' controls src='{{ $audio->url }}' class='w-full mb-4'></audio>
        @else
            <div class='p-4 text-white bg-danger-500 rounded-lg mb-4'>
                <p>خطا: لینک فایل صوتی (URL) برای این آیتم ثبت نشده است.</p>
            </div>
        @endif

        <div class='mb-4 p-4 border rounded-lg bg-gray-50'>
            <h3 class='font-bold mb-2'>راهنما:</h3>
            <ul class='list-disc list-inside text-sm'>
                <li>ابتدا صوت را پخش کنید.</li>
                <li>وقتی گوینده شروع به خواندن یک کلمه کرد، روی آن کلمه <strong>کلیک چپ</strong> کنید. (زمان شروع ثبت می‌شود)</li>
                <li>وقتی خواندن کلمه تمام شد، روی همان کلمه <strong>کلیک راست</strong> کنید. (زمان پایان ثبت می‌شود)</li>
                 <li>برای پاک کردن زمان یک کلمه، روی آن <strong>دوبار کلیک</strong> کنید.</li>
                <li>کلمات زمان‌بندی شده به رنگ سبز در می‌آیند.</li>
            </ul>
        </div>

        <div id='word-container' class='p-4 border rounded-lg leading-loose' dir='rtl'>
            @foreach($words as $index => $word)
                <span 
                    class='word p-1 cursor-pointer hover:bg-primary-200' 
                    data-index='{{ $index }}'
                >{{ $word }}</span>
            @endforeach
        </div>

        <div class='flex justify-end mt-6'>
            <button type='button' onclick='save()' class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700'>
                ذخیره و بستن
            </button>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('livewire:load', function () {
            const audioPlayer = document.getElementById('audioPlayer');
            const words = document.querySelectorAll('.word');
            let timingData = @json($timingData);

            // Function to update word colors based on timing data
            function updateWordStyles() {
                words.forEach(wordEl => {
                    const index = wordEl.dataset.index;
                    if (timingData[index] && timingData[index].start && timingData[index].end) {
                        wordEl.classList.add('bg-success-300');
                        wordEl.classList.remove('bg-warning-300');
                    } else if (timingData[index] && timingData[index].start) {
                        wordEl.classList.add('bg-warning-300');
                        wordEl.classList.remove('bg-success-300');
                    } else {
                        wordEl.classList.remove('bg-success-300', 'bg-warning-300');
                    }
                });
            }
            
            // Initial styling
            updateWordStyles();

            words.forEach(wordEl => {
                const index = parseInt(wordEl.dataset.index);

                // Left-click to set start time
                wordEl.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (!audioPlayer.src) return;
                    const currentTime = audioPlayer.currentTime * 1000; // in milliseconds
                    if (!timingData[index]) {
                        timingData[index] = {};
                    }
                    timingData[index].start = Math.round(currentTime);
                    console.log(`Set start for word ${index} to ${currentTime}`);
                    updateWordStyles();
                });

                // Right-click to set end time
                wordEl.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    if (!audioPlayer.src) return;
                    const currentTime = audioPlayer.currentTime * 1000; // in milliseconds
                    if (timingData[index] && timingData[index].start) {
                         timingData[index].end = Math.round(currentTime);
                         console.log(`Set end for word ${index} to ${currentTime}`);
                    } else {
                        console.log(`Cannot set end time. Set start time first for word ${index}.`);
                    }
                    updateWordStyles();
                });

                // Double-click to clear timing
                 wordEl.addEventListener('dblclick', (e) => {
                    e.preventDefault();
                    if (timingData[index]) {
                        delete timingData[index];
                        console.log(`Cleared timing for word ${index}`);
                        updateWordStyles();
                    }
                });
            });
            
            // Make the save function global
            window.save = function() {
                 // Filter out any potential null/empty values from the array
                const cleanTimingData = timingData.filter(item => item !== null);
                @this.call('saveTimingData', cleanTimingData);
            }
        });
    </script>
    @endpush
</div>
