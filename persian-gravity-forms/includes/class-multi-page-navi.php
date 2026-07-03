<?php

defined( 'ABSPATH' ) || exit;

class GFPersian_Multipage_Navigation extends GFPersian_Core {

	public function __construct() {

		if ( is_admin() || $this->option( 'multipage_nav', '1' ) != '1' ) {
			return;
		}

		add_filter( 'gform_pre_render', [ $this, 'output_navigation_script' ], 100, 2 );
	}

	public function add_last_page_input( $tag, $form ) {

		if ( str_contains( $tag, 'gform_multi_page_nav_last_page_reached' ) ) {
			return $tag;
		}

		return $tag .= '<input id="gform_multi_page_nav_last_page_reached" name="gform_multi_page_nav_last_page_reached" value="' . esc_attr( $this->is_last_page( $form ) ? '1' : '0' ) . '" type="hidden" />';
	}

	public function output_navigation_script( $form, $is_ajax ) {

		if ( ! isset( $form['pagination']['pages'], $form['pagination']['type'] ) || ! is_array( $form['pagination']['pages'] ) || count( $form['pagination']['pages'] ) <= 1 || $form['pagination']['type'] !== 'steps' ) {
			return $form;
		}

		$this->register_script( $form );

		add_filter( "gform_form_tag_{$form['id']}", [ $this, 'add_last_page_input' ], 10, 2 );

		return $form;
	}

	public function register_script( $form ) {
		$page_number = GFFormDisplay::get_current_page( $form['id'] );
		$last_page   = count( $form['pagination']['pages'] );

		$args = wp_json_encode( [
			'formId'             => $form['id'],
			'currentPage'        => $page_number,
			'lastPage'           => $last_page,
			'activateOnLastPage' => $this->option( 'multipage_nav_last', '1' ) === '1',
		] );
		?>

		<script type="text/javascript">
            (function () {

                const args = <?php echo $args; ?>;

                function gformMultiPageNavInit() {
                    const form = document.querySelector('#gform_' + args.formId);
                    const stepContainer = document.querySelector('#gf_page_steps_' + args.formId);

                    if (!form || !stepContainer) return;

                    const steps = stepContainer.querySelectorAll('.gf_step');
                    const currentPage = parseInt(form.querySelector('#gform_source_page_number_' + args.formId)?.value || 1);
                    const lastPage = steps.length;
                    const isLastPage = currentPage >= lastPage;

                    let hidden = form.querySelector('#gform_multi_page_nav_last_page_reached');

                    if (!hidden) {

                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.id = 'gform_multi_page_nav_last_page_reached';
                        hidden.name = 'gform_multi_page_nav_last_page_reached';
                        form.appendChild(hidden);

                    }

                    hidden.value = isLastPage ? '1' : '0';

                    steps.forEach(step => {

                        const numberElem = step.querySelector('.gf_step_number');

                        if (!numberElem) return;

                        const stepNumber = parseInt(numberElem.textContent);

                        step.classList.remove('gform_multi_page_nav-step-current', 'gform_multi_page_nav-step-linked');

                        if (stepNumber === currentPage) {
                            step.classList.add('gform_multi_page_nav-step-current');
                            numberElem.style.cursor = 'default';
                            return;
                        } else {
                            step.classList.add('gform_multi_page_nav-step-linked');
                        }

                        const allowClick = !args.activateOnLastPage || isLastPage;
                        numberElem.style.cursor = allowClick ? 'pointer' : 'default';

                        const newElem = numberElem.cloneNode(true);
                        numberElem.parentNode.replaceChild(newElem, numberElem);

                        if (allowClick) {

                            newElem.addEventListener('click', function (e) {
                                e.preventDefault();

                                let hiddenChange = form.querySelector('input[name="gform_multi_page_nav_page_change"]');

                                if (!hiddenChange) {

                                    hiddenChange = document.createElement('input');
                                    hiddenChange.type = 'hidden';
                                    hiddenChange.name = 'gform_multi_page_nav_page_change';
                                    hiddenChange.value = '1';
                                    form.appendChild(hiddenChange);

                                }

                                const targetInput = form.querySelector('input[name="gform_target_page_number_' + args.formId + '"]');
                                if (targetInput) targetInput.value = stepNumber;

                                if (typeof jQuery !== 'undefined' && jQuery(form).data('gfAjax')) {
                                    jQuery(form).trigger('submit');
                                } else {
                                    form.submit();
                                }

                            });

                        }

                    });

                }

                document.addEventListener('DOMContentLoaded', gformMultiPageNavInit);

                document.addEventListener('gform/post_render', function (event) {
                    if (event.detail.formId === args.formId) {
                        gformMultiPageNavInit();
                    }
                });

            })();
		</script>

		<?php
	}

	public function is_last_page( ?array $form ): bool {
		if ( is_null( $form ) ) {
			return false;
		}

		$page_number = GFFormDisplay::get_current_page( $form['id'] );
		$last_page   = count( $form['pagination']['pages'] );

		return $page_number >= $last_page;
	}

}

new GFPersian_Multipage_Navigation();
