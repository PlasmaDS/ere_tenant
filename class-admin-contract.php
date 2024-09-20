<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'ERE_Admin_Contract' ) ) {
    /**
     * Class ERE_Admin_Contract
     */
class ERE_Admin_Contract {

    public function __construct() {
        // Otros hooks...
        add_action('pre_get_posts', [__CLASS__, 'filter_properties_by_contract_status']);
    }

    /**
     * Inicializar la clase y agregar menú al panel de administración.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('manage_edit-property_columns', [__CLASS__, 'register_custom_column_titles']);
        add_action('manage_property_posts_custom_column', [__CLASS__, 'display_custom_column'], 10, 2);
        add_action('admin_init', [__CLASS__, 'handle_contract_renewal']);
        add_action('admin_init', [__CLASS__, 'delete_contract']);
        add_filter('manage_edit-property_sortable_columns', [__CLASS__, 'sortable_columns']);
        add_filter('request', [__CLASS__, 'column_orderby']);
        add_action('restrict_manage_posts', [__CLASS__, 'add_contract_status_filter']); // Aquí se mantiene
        add_filter('post_row_actions', [__CLASS__, 'modify_list_row_actions'], 10, 2);
        
        // Encola el script JavaScript
        add_action('admin_footer', [__CLASS__, 'add_inline_script']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        
        add_action('wp_ajax_get_contract_data', [__CLASS__, 'get_contract_data']);
        add_action('wp_ajax_save_contract_changes', [__CLASS__, 'save_contract_changes']);
        add_action('wp_ajax_nopriv_save_contract_changes', [__CLASS__, 'save_contract_changes']);
        
        add_action('restrict_manage_posts', [__CLASS__, 'add_contract_status_filter']);
        add_action('pre_get_posts', [__CLASS__, 'filter_properties_by_contract_status']);
    }



        public static function add_inline_script() {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Manejar el clic en el botón para editar contrato
                $(document).on('click', '.edit-contract', function(event) {
                    let postId = $(this).data('post-id');
                    showEditContractForm(event, postId);
                });
        
                window.showEditContractForm = function(event, postId) {
                    event.preventDefault();
        
                    // Verifica si el diálogo ya existe y está inicializado
                    let dialogElement = $('#edit-contract-form');
                    if (dialogElement.length > 0 && dialogElement.data('ui-dialog')) {
                        // Si el diálogo está abierto, lo cerramos
                        if (dialogElement.dialog("isOpen")) {
                            dialogElement.dialog('close');
                        }
                        return; // Salimos de la función para evitar la apertura de uno nuevo
                    }
        
                    $.ajax({
                        url: ajaxurl,
                        type: 'GET',
                        data: {
                            action: 'get_contract_data',
                            post_id: postId
                        },
                        success: function(response) {
                            if (response.success) {
                                let data = response.data;

                                let formHtml = `
                                    <div id="edit-contract-form" title="Editar Contrato">
                                        <form id="edit-contract">
                                            <label for="_contract_creation_date">Fecha de Creación:</label>
                                            <input type="date" name="_contract_creation_date" id="_contract_creation_date" value="${data.contract_creation_date}" required><br>
                                            
                                            <label for="_monthly_rent">Renta Mensual:</label>
                                            <input type="text" name="_monthly_rent" id="_monthly_rent" value="${data.monthly_rent}" required><br>
                                            
                                            <label for="_increase_rate">Tasa de Incremento:</label>
                                            <input type="text" name="_increase_rate" id="_increase_rate" value="${data.increase_rate}" required><br>
                                            
                                            <label for="_increase_frequency">Frecuencia de Incremento:</label>
                                            <input type="text" name="_increase_frequency" id="_increase_frequency" value="${data.increase_frequency}" required><br>
                                            
                                            <label for="_contract_expiration_date">Fecha de Expiración:</label>
                                            <input type="date" name="_contract_expiration_date" id="_contract_expiration_date" value="${data.contract_expiration_date}" required><br>
                                            
                                            <input type="hidden" name="post_id" value="${postId}">
                                            <button type="submit" id="save-contract">Guardar</button>
                                        </form>
                                    </div>
                                `;
        
                                // Agregar el formulario al DOM
                                $('body').append(formHtml);
                                $('#edit-contract-form').dialog({
                                    modal: true,
                                    close: function() {
                                        $(this).dialog("destroy").remove(); // Destruir el diálogo al cerrarlo
                                    },
                                    buttons: {
                                        "Cerrar": function() {
                                            $(this).dialog("close");
                                        }
                                    }
                                });
        
                                // Manejador de envío del formulario
                                $('#edit-contract').off('submit').on('submit', function(e) {
                                    e.preventDefault();
                                    let formData = $(this).serialize();
        
                                    $.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'save_contract_changes',
                                            form_data: formData
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                alert('Contrato actualizado correctamente.');
                                                $('#edit-contract-form').dialog('close').remove();
                                                // Actualiza la fila correspondiente
                                                let newData = response.data;
                                                let row = $('tr[data-id="' + postId + '"]');
                                                row.find('td').eq(2).text(newData.contract_creation_date);
                                                row.find('td').eq(3).text(newData.monthly_rent);
                                                row.find('td').eq(4).text(newData.increase_frequency + ' meses');
                                                row.find('td').eq(5).text(newData.increase_rate + '%');
                                                row.find('td').eq(6).text(newData.contract_expiration_date);
                                            } else {
                                                alert('Error al actualizar el contrato: ' + response.data);
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.log(xhr.responseText);
                                            alert('Error al actualizar el contrato: ' + error);
                                        }
                                    });
                                });
                            } else {
                                alert('Error al obtener los datos del contrato.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log(xhr.responseText);
                            alert('Error al obtener los datos del contrato: ' + error);
                        }
                    });
                };
            });
            </script>
            <?php
        }
        
        public static function get_contract_data() {
            // Verifica el nonce si es necesario para mayor seguridad
            // check_ajax_referer('your_nonce_action', 'nonce');
        
            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        
            if (empty($post_id)) {
                wp_send_json_error('ID de contrato inválido.');
            }
        
            $contract_creation_date = get_post_meta($post_id, '_contract_creation_date', true);
            $monthly_rent = get_post_meta($post_id, '_monthly_rent', true);
            $increase_rate = get_post_meta($post_id, '_increase_rate', true);
            $increase_frequency = get_post_meta($post_id, '_increase_frequency', true);
            $contract_expiration_date = get_post_meta($post_id, '_contract_expiration_date', true);
            $property_address = get_post_meta($post_id, 'real_estate_property_address', true);
        
            $data = [
                'contract_creation_date' => $contract_creation_date,
                'monthly_rent' => $monthly_rent,
                'increase_rate' => $increase_rate,
                'increase_frequency' => $increase_frequency,
                'contract_expiration_date' => $contract_expiration_date,
                'property_address' => $property_address
            ];
        
            wp_send_json_success($data);
        }

        public static function enqueue_admin_scripts() {
            wp_enqueue_script( 'jquery-ui-dialog' );
            wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );
        }

        /**
         * Agregar menú para gestión de contratos
         */
        public static function add_admin_menu() {
            add_menu_page(
                'Gestión de Contratos',  // Título de la página
                'Contratos',             // Título del menú
                'manage_options',        // Capacidad necesaria para ver el menú
                'admin-contracts',       // Slug del menú
                [ __CLASS__, 'contracts_page' ], // Función que renderiza la página
                'dashicons-list-view',   // Icono del menú
                6                        // Posición en el menú
            );
        }

        /**
         * Renderiza la página de contratos en el menú de administración.
         */
        public static function contracts_page() {
            // Argumentos de la consulta para obtener solo los contratos activos
            $args = [
                'post_type'      => 'property',
                'posts_per_page' => -1,
                'meta_query'     => [
                    [
                        'key'     => '_contract_active',
                        'value'   => '1', // Solo contratos activos
                        'compare' => '='
                    ]
                ],
            ];
        
            // Añadir los filtros a los argumentos de la consulta si se aplican
            self::apply_filters_to_query($args);
        
            // Consulta los contratos
            $contracts = new WP_Query($args);
        
            // Mostrar filtros
            echo '<div class="wrap">';
            echo '<h1>Gestión de Contratos</h1>';
        
            // Filtros para inquilino y dirección de la propiedad
            self::render_filters_form();
        
            // Mostrar tabla de contratos
            self::render_contracts_table($contracts);
        
            echo '</div>';
        
            // Resetea los datos post
            wp_reset_postdata();
        }

        /**
         * Aplica los filtros a los argumentos de la consulta.
         *
         * @param array $args
         */
        private static function apply_filters_to_query( &$args ) {
            if ( isset($_GET['tenant_name']) || isset($_GET['property_address']) ) {
                $meta_query = ['relation' => 'AND'];
        
                // Filtrar por nombre del inquilino
                if ( !empty($_GET['tenant_name']) ) {
                    $tenant_name = sanitize_text_field($_GET['tenant_name']);
                    $tenant_ids = self::get_tenant_ids($tenant_name);
        
                    if ( !empty($tenant_ids) ) {
                        $meta_query[] = [
                            'key'     => '_assigned_tenant',
                            'value'   => $tenant_ids,
                            'compare' => 'IN',
                        ];
                    }
                }
        
                // Filtrar por dirección de la propiedad
                if ( !empty($_GET['property_address']) ) {
                    $property_address = sanitize_text_field($_GET['property_address']);
                    $meta_query[] = [
                        'key'     => 'real_estate_property_address',
                        'value'   => $property_address,
                        'compare' => 'LIKE',
                    ];
                }
        
                // Agregar la condición para contratos activos
                $meta_query[] = [
                    'key'     => '_contract_active',
                    'value'   => '1',
                    'compare' => '=',
                ];
        
                $args['meta_query'] = $meta_query;
            }
        }

        /**
         * Obtiene los IDs de los inquilinos que coinciden con el nombre.
         *
         * @param string $tenant_name
         * @return array
         */
        private static function get_tenant_ids( $tenant_name ) {
            $tenant_query = new WP_User_Query([
                'search'         => '*' . esc_attr($tenant_name) . '*',
                'search_columns' => ['display_name', 'user_nicename'],
            ]);
            return wp_list_pluck($tenant_query->get_results(), 'ID');
        }

        /**
         * Renderiza el formulario de filtros.
         */
        private static function render_filters_form() {
            echo '<form method="GET" class="filters-form" style="margin-bottom: 20px;">';
            echo '<input type="hidden" name="post_type" value="property" />';
            echo '<input type="hidden" name="page" value="admin-contracts" />';
            
            // Estilo de los inputs
            echo '<input type="text" class="filter-input" placeholder="' . esc_attr__( 'Nombre', 'essential-real-estate' ) . '" name="tenant_name" value="' . esc_attr( isset($_GET['tenant_name']) ? $_GET['tenant_name'] : '' ) . '" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; flex: 1;" />';
            
            echo '<input type="text" class="filter-input" placeholder="' . esc_attr__( 'Dirección', 'essential-real-estate' ) . '" name="property_address" value="' . esc_attr( isset($_GET['property_address']) ? $_GET['property_address'] : '' ) . '" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; flex: 1;" />';
            
            // Estilo del botón de submit
            echo '<input type="submit" value="' . esc_attr__( 'Buscar', 'essential-real-estate' ) . '" style="padding: 8px 12px; background-color: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.3s;" />';
            echo '</form>';
        }
        
        /**
         * Renderiza la tabla de contratos.
         *
         * @param WP_Query $contracts
         */
        private static function render_contracts_table( $contracts ) {
            if ( $contracts->have_posts() ) {
                echo '<table class="widefat fixed striped" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
                echo '<thead>';
                echo '<tr style="background-color: #f1f1f1;">';
                echo '<th>' . esc_html__( 'Inquilino', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Dirección', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Inicio del contrato', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Renta mensual', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Frecuancia de aumento', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Porcentaje de aumento', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Expiración del contrato', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Extender (+2 años)', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Editar o renovar', 'essential-real-estate' ) . '</th>';
                echo '<th>' . esc_html__( 'Eliminar', 'essential-real-estate' ) . '</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
        
                while ( $contracts->have_posts() ) {
                    $contracts->the_post();
                    self::render_contract_row();
                }
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p style="color: #666;">' . esc_html__( 'No hay contratos disponibles.', 'essential-real-estate' ) . '</p>';
            }
        }


        /**
         * Renderiza una fila de contrato en la tabla.
         */
        private static function render_contract_row() {
            $tenant_id = get_post_meta(get_the_ID(), '_assigned_tenant', true);
            $tenant_info = get_userdata($tenant_id);
        
            // Obtener el nombre del inquilino desde los metadatos
            $tenant_first_name = $tenant_info ? esc_html(get_user_meta($tenant_id, 'first_name', true)) : esc_html__('Inquilino no encontrado', 'essential-real-estate');
            $tenant_last_name = $tenant_info ? esc_html(get_user_meta($tenant_id, 'last_name', true)) : esc_html__('Inquilino no encontrado', 'essential-real-estate');
            $tenant_name = $tenant_first_name . ' ' . $tenant_last_name;
        
            // Obtener la dirección de la propiedad
            $property_address = get_post_meta(get_the_ID(), 'real_estate_property_address', true);
        
            $contract_creation_date = get_post_meta(get_the_ID(), '_contract_creation_date', true);
            $monthly_rent = get_post_meta(get_the_ID(), '_monthly_rent', true);
            $increase_frequency = get_post_meta(get_the_ID(), '_increase_frequency', true);
            $increase_rate = get_post_meta(get_the_ID(), '_increase_rate', true);
            $contract_expiration_date = get_post_meta(get_the_ID(), '_contract_expiration_date', true);
        
            echo '<tr data-id="' . esc_attr(get_the_ID()) . '">'; // Agrega el atributo data-id con el ID del contrato
            echo '<td>' . $tenant_name . '</td>'; // Muestra el nombre del inquilino
            echo '<td>' . esc_html($property_address) . '</td>'; // Muestra la dirección de la propiedad
            echo '<td>' . esc_html($contract_creation_date) . '</td>';
            echo '<td>' . esc_html($monthly_rent) . '</td>';
            echo '<td>' . esc_html($increase_frequency) . ' meses</td>';
            echo '<td>' . esc_html($increase_rate) . '%</td>';
            echo '<td>' . esc_html($contract_expiration_date) . '</td>';
            
            // Botón para extender contrato
            $renew_url = admin_url('admin.php?page=admin-contracts&action=renew_contract&contract_id=' . get_the_ID());
            echo '<td><a href="' . esc_url($renew_url) . '" class="button" style="background-color: #27ae60; color: white; border: none; text-decoration: none; transition: background-color 0.3s;" onmouseover="this.style.backgroundColor=\'#2ecc71\'" onmouseout="this.style.backgroundColor=\'#27ae60\'">' . esc_html__('Extender Contrato', 'essential-real-estate') . '</a></td>';
            
            // Botón para editar-renovar contrato
            $edit_inline_url = admin_url('admin-ajax.php?action=get_quick_edit_contract&post_id=' . get_the_ID());
            echo '<td><a href="#" class="button edit-contract" data-post-id="' . get_the_ID() . '" style="background-color: #3498db; color: white; border: none; text-decoration: none; transition: background-color 0.3s;" onmouseover="this.style.backgroundColor=\'#2980b9\'" onmouseout="this.style.backgroundColor=\'#3498db\'">' . esc_html__('Editar/Renovar', 'essential-real-estate') . '</a></td>';

            // Botón para cancelar contrato
            $delete_url = admin_url('admin.php?page=admin-contracts&action=delete_contract&contract_id=' . get_the_ID());
            echo '<td><a href="' . esc_url($delete_url) . '" class="button" style="background-color: #c0392b; color: white; border: none; text-decoration: none; transition: background-color 0.3s;" onmouseover="this.style.backgroundColor=\'#e74c3c\'" onmouseout="this.style.backgroundColor=\'#c0392b\'" onclick="return confirm(\'¿Estás seguro de que deseas cancelar este contrato?\');">' . esc_html__('Cancelar Contrato', 'essential-real-estate') . '</a></td>';
            echo '</tr>';
        }

        public static function handle_contract_renewal() {
            if (isset($_GET['action']) && $_GET['action'] === 'renew_contract' && isset($_GET['contract_id'])) {
                $contract_id = intval($_GET['contract_id']);
        
                // Aquí puedes realizar la lógica para renovar el contrato.
                // Esto puede incluir la actualización de la fecha de expiración, entre otros detalles.
        
                // Por ejemplo, renovar el contrato podría ser establecer una nueva fecha de expiración:
                $current_expiration_date = get_post_meta($contract_id, '_contract_expiration_date', true);
                $new_expiration_date = date('Y-m-d', strtotime($current_expiration_date . ' +2 year')); // Añadir un año
        
                update_post_meta($contract_id, '_contract_expiration_date', $new_expiration_date);
        
                // Redirigir a la página de contratos después de renovar
                wp_redirect(admin_url('admin.php?page=admin-contracts&renewed=1'));
                exit;
            }
        }
        
        /**
         * Función para cancelar un contrato y vaciar los metadatos asociados.
         */
        public static function delete_contract() {
            if ( isset($_GET['action']) && $_GET['action'] === 'delete_contract' && isset($_GET['contract_id']) ) {
                $contract_id = intval($_GET['contract_id']);
        
                // Verificar si el contrato existe
                if ( get_post($contract_id) ) {
        
                    // Metadatos a vaciar
                    $meta_keys = [
                        '_contract_duration',
                        '_monthly_rent',
                        '_increase_rate',
                        '_increase_frequency',
                        '_contract_expiration_date',
                        '_contract_creation_date',
                        '_contract_active',
                        '_assigned_tenant',
                        '_last_invoice_date',
                    ];
        
                    // Vaciar cada metadato
                    foreach ( $meta_keys as $meta_key ) {
                        update_post_meta($contract_id, $meta_key, '');
                    }
        
                    // Redirigir a la página de contratos con un mensaje de éxito
                    wp_redirect(admin_url('admin.php?page=admin-contracts&message=contract_deleted'));
                    exit;
                }
            }
        }

        // Funciones para gestionar columnas, filtros y acciones en la lista de propiedades
        public static function register_custom_column_titles( $columns ) {
            $columns['cb']                      = "<input type=\"checkbox\" />";
            $columns['title']                   = esc_html__( 'Contract', 'essential-real-estate' );
            $columns['_assigned_tenant']        = esc_html__( 'Tenant', 'essential-real-estate' );
            $columns['_contract_creation_date'] = esc_html__( 'Creation Date', 'essential-real-estate' );
            $columns['_monthly_rent']           = esc_html__( 'Monthly Rent', 'essential-real-estate' );
            $columns['_contract_expiration_date'] = esc_html__( 'Expiration Date', 'essential-real-estate' );
            $columns['property_address']         = esc_html__( 'Property Address', 'essential-real-estate' ); // Agregar columna para dirección
        
            return $columns;
        }

        public static function display_custom_column( $column, $post_id ) {
            switch ( $column ) {
                case '_assigned_tenant':
                    $tenant_id = get_post_meta( $post_id, '_assigned_tenant', true );
                    $tenant_info = get_userdata( $tenant_id );
                    $tenant_first_name = $tenant_info ? esc_html( get_user_meta( $tenant_id, 'first_name', true ) ) : 'N/A';
                    $tenant_last_name = $tenant_info ? esc_html( get_user_meta( $tenant_id, 'last_name', true ) ) : 'N/A';
                    echo esc_html( $tenant_first_name . ' ' . $tenant_last_name );
                    break;
        
                case '_contract_creation_date':
                    echo esc_html( get_post_meta( $post_id, '_contract_creation_date', true ) );
                    break;
        
                case '_monthly_rent':
                    echo esc_html( get_post_meta( $post_id, '_monthly_rent', true ) );
                    break;
        
                case '_contract_expiration_date':
                    echo esc_html( get_post_meta( $post_id, '_contract_expiration_date', true ) );
                    break;
        
                case 'property_address': // Mostrar la dirección aquí
                    echo esc_html( get_post_meta( $post_id, 'real_estate_property_address', true ) );
                    break;
            }
        }

        public static function sortable_columns( $columns ) {
            $columns['_contract_creation_date'] = 'contract_creation_date';
            $columns['_monthly_rent'] = 'monthly_rent';
            $columns['_contract_expiration_date'] = 'contract_expiration_date';

            return $columns;
        }

        public static function column_orderby( $vars ) {
            if ( isset( $vars['orderby'] ) ) {
                switch ( $vars['orderby'] ) {
                    case 'contract_creation_date':
                        $vars = array_merge( $vars, ['meta_key' => '_contract_creation_date', 'orderby' => 'meta_value'] );
                        break;

                    case 'monthly_rent':
                        $vars = array_merge( $vars, ['meta_key' => '_monthly_rent', 'orderby' => 'meta_value_num'] );
                        break;

                    case 'contract_expiration_date':
                        $vars = array_merge( $vars, ['meta_key' => '_contract_expiration_date', 'orderby' => 'meta_value'] );
                        break;
                }
            }
            return $vars;
        }

		/**
		 * Add filters to the posts list
		 */
		public static function filter_restrict_manage_contract() {
			global $typenow;
			if ( 'property' === $typenow ) {
				$tenant_name = isset($_GET['tenant_name']) ? esc_attr($_GET['tenant_name']) : '';
				$property_address = isset($_GET['property_address']) ? esc_attr($_GET['property_address']) : '';

				// Filter by tenant name
				echo '<input type="text" placeholder="' . esc_attr__( 'Nombre', 'essential-real-estate' ) . '" name="tenant_name" value="' . esc_attr( $tenant_name ) . '" />';
				// Filter by property address
				echo '<input type="text" placeholder="' . esc_attr__( 'Dirección', 'essential-real-estate' ) . '" name="property_address" value="' . esc_attr( $property_address ) . '" />';
			}
		}

        public static function add_contract_status_filter() {
            // Aquí va el código para agregar el filtro
            ?>
            <select name="contract_status" id="contract_status">
                <option value=""><?php _e('Con/Sin Contrato', 'textdomain'); ?></option>
                <option value="active" <?php selected(isset($_GET['contract_status']) && $_GET['contract_status'] === 'active'); ?>><?php _e('Con Contrato', 'textdomain'); ?></option>
                <option value="inactive" <?php selected(isset($_GET['contract_status']) && $_GET['contract_status'] === 'inactive'); ?>><?php _e('Sin Contrato', 'textdomain'); ?></option>
            </select>
            <?php
        }
        
        public static function filter_properties_by_contract_status($query) {
            global $pagenow;
    
            if (is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'property') {
                $meta_query = [];
    
                if (isset($_GET['contract_status']) && !empty($_GET['contract_status'])) {
                    $contract_status = sanitize_text_field($_GET['contract_status']);
                    if ($contract_status === 'inactive') {
                        $meta_query[] = [
                            'relation' => 'OR',
                            [
                                'key'     => '_contract_active',
                                'value'   => '0',
                                'compare' => '='
                            ],
                            [
                                'key'     => '_contract_active',
                                'compare' => 'NOT EXISTS' // Para propiedades donde el metadato no existe
                            ],
                            [
                                'key'     => '_contract_active',
                                'value'   => '',
                                'compare' => '=' // Para propiedades donde el valor es NULL
                            ]
                        ];
                    } elseif ($contract_status === 'active') {
                        $meta_query[] = [
                            'key'     => '_contract_active',
                            'value'   => '1',
                            'compare' => '='
                        ];
                    }
                }
    
                if (!empty($meta_query)) {
                    $query->set('meta_query', $meta_query);
                }
            }
        }

		/**
		 * Modify list row actions for each post
		 *
		 * @param array $actions
		 * @param WP_Post $post
		 *
		 * @return array
		 */
		public static function modify_list_row_actions( $actions, $post ) {
			if ( $post->post_type === 'property' ) {
				$actions['edit'] = '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Edit', 'essential-real-estate' ) . '</a>';
				$actions['view'] = '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . esc_html__( 'View', 'essential-real-estate' ) . '</a>';
			}

			return $actions;
		}
		
		private static function update_contract($property_id, $contract_creation_date, $monthly_rent, $increase_rate, $increase_frequency, $contract_expiration_date) {
            // Verifica si la propiedad existe
            if (get_post_status($property_id)) {
                // Actualizar los datos del contrato
                update_post_meta($property_id, '_contract_creation_date', $contract_creation_date);
                update_post_meta($property_id, '_monthly_rent', $monthly_rent);
                update_post_meta($property_id, '_increase_rate', $increase_rate);
                update_post_meta($property_id, '_increase_frequency', $increase_frequency);
                update_post_meta($property_id, '_contract_expiration_date', $contract_expiration_date);
                
                return true;
            } else {
                return new WP_Error('invalid_property', __('Error: ID de propiedad no válido.'));
            }
        }

        public static function save_contract_changes() {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('No tienes permisos para realizar esta acción.');
            }
        
            if (isset($_POST['form_data'])) {
                parse_str($_POST['form_data'], $form_data); // Convierte la cadena serializada en un array PHP
        
                $post_id = isset($form_data['post_id']) ? intval($form_data['post_id']) : 0;
                $contract_creation_date = sanitize_text_field($form_data['_contract_creation_date']);
                $monthly_rent = sanitize_text_field($form_data['_monthly_rent']);
                $increase_rate = sanitize_text_field($form_data['_increase_rate']);
                $increase_frequency = sanitize_text_field($form_data['_increase_frequency']);
                $contract_expiration_date = sanitize_text_field($form_data['_contract_expiration_date']);
        
                if (get_post_status($post_id)) {
                    // Actualiza los metadatos del contrato
                    update_post_meta($post_id, '_contract_creation_date', $contract_creation_date);
                    update_post_meta($post_id, '_monthly_rent', $monthly_rent);
                    update_post_meta($post_id, '_increase_rate', $increase_rate);
                    update_post_meta($post_id, '_increase_frequency', $increase_frequency);
                    update_post_meta($post_id, '_contract_expiration_date', $contract_expiration_date);
        
                    // Prepara los nuevos datos para la respuesta
                    $new_data = [
                        'contract_creation_date' => $contract_creation_date,
                        'monthly_rent' => $monthly_rent,
                        'increase_rate' => $increase_rate,
                        'increase_frequency' => $increase_frequency,
                        'contract_expiration_date' => $contract_expiration_date,
                    ];
                    
                    // Envía los nuevos datos como respuesta exitosa
                    wp_send_json_success($new_data);
                } else {
                    wp_send_json_error('Propiedad no encontrada.');
                }
            } else {
                wp_send_json_error('Datos del formulario no recibidos.');
            }
        
            wp_die(); // Detiene la ejecución en AJAX
        }

    }
    
    // Inicializa la clase
    ERE_Admin_Contract::init();
}
